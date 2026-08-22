<?php

namespace App\Services\Dashboard;

use App\Models\JournalLine;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\Member;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceCoa;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use Illuminate\Support\Carbon;

/**
 * KPI Dashboard Utama (PRD §15) — semua angka diturunkan langsung dari
 * ledger/rekening operasional (single source of truth, LED-09), bukan
 * dihitung/disimpan terpisah. `branchId = null` berarti konsolidasi seluruh
 * cabang (hanya diizinkan untuk user Cabang Scope All Branch — ditegakkan
 * di controller, bukan di sini).
 */
class MainDashboardService
{
    public function summary(?int $branchId = null): array
    {
        $membersByType = Member::query()
            ->join('member_types', 'members.member_type_id', '=', 'member_types.id')
            ->when($branchId, fn ($q) => $q->where('members.branch_id', $branchId))
            ->selectRaw('member_types.name as type_name, count(*) as total')
            ->groupBy('member_types.name')
            ->get();

        $savingsByProduct = SavingsAccount::query()
            ->where('savings_accounts.status', 'aktif')
            ->join('savings_products', 'savings_accounts.savings_product_id', '=', 'savings_products.id')
            ->when($branchId, fn ($q) => $q->where('savings_accounts.branch_id', $branchId))
            ->selectRaw('savings_products.name as product_name, sum(balance) as total')
            ->groupBy('savings_products.name')
            ->get();

        $loanBase = Loan::query()
            ->where('status', 'dicairkan')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $totalLoanOutstanding = (float) (clone $loanBase)->sum('principal_amount');
        $nplOutstanding = (float) (clone $loanBase)->whereIn('collectibility', ['kurang_lancar', 'diragukan', 'macet'])->sum('principal_amount');
        $nplRatio = $totalLoanOutstanding > 0 ? round($nplOutstanding / $totalLoanOutstanding * 100, 2) : 0.0;

        $loanByCollectibility = (clone $loanBase)
            ->selectRaw('collectibility, count(*) as count, sum(principal_amount) as total')
            ->groupBy('collectibility')
            ->get();

        $shuBreakdown = $this->shuBreakdown($branchId);

        return [
            'members_by_type' => $membersByType,
            'total_members' => (int) $membersByType->sum('total'),
            'savings_by_product' => $savingsByProduct,
            'total_savings' => (float) $savingsByProduct->sum('total'),
            'total_loan_outstanding' => $totalLoanOutstanding,
            'loan_outstanding_by_collectibility' => $loanByCollectibility,
            'npl_ratio' => $nplRatio,
            'shu_running' => $shuBreakdown['shu'],
            'shu_breakdown' => $shuBreakdown,
            'problematic_loans' => (clone $loanBase)
                ->whereIn('collectibility', ['kurang_lancar', 'diragukan', 'macet'])
                ->with('member')
                ->orderByDesc('principal_amount')
                ->limit(8)
                ->get(),
            'due_installments' => LoanSchedule::query()
                ->whereIn('status', ['belum_bayar', 'sebagian'])
                ->whereHas('loan', fn ($q) => $q->where('status', 'dicairkan')->when($branchId, fn ($q2) => $q2->where('branch_id', $branchId)))
                ->with('loan.member')
                ->orderBy('due_date')
                ->limit(8)
                ->get(),
            // Every status, not just $loanBase's "dicairkan" — this is a
            // feed of the most recently touched loans (submitted, decided,
            // disbursed, cancelled), not a snapshot of outstanding ones.
            'recent_loan_activity' => Loan::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->with('member')
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get(),
            'daily_activity' => $this->dailyActivity($branchId),
        ];
    }

    /**
     * Simpanan & Pinjaman are activity that actually happened (transaksi
     * setor/tarik, tanggal pencairan). "Angsuran" is deliberately based on
     * `due_date` (jadwal jatuh tempo), not payments received — this app has
     * no loan-repayment-recording feature yet (`loan_schedules.paid_amount`
     * is only ever written as 0 at schedule generation), so due-amount is
     * the only real, populated daily signal available for installments.
     */
    private function dailyActivity(?int $branchId = null): array
    {
        $windowStart = now()->subDays(13)->startOfDay();
        $days = collect(range(13, 0))->map(fn (int $i) => now()->subDays($i)->toDateString());

        $savingsByDay = SavingsTransaction::query()
            ->whereNull('cancelled_at')
            ->where('created_at', '>=', $windowStart)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $loanByDay = Loan::query()
            ->whereNotNull('disbursed_at')
            ->where('disbursed_at', '>=', $windowStart->toDateString())
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('disbursed_at as day, SUM(principal_amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $installmentByDay = LoanSchedule::query()
            ->where('due_date', '>=', $windowStart->toDateString())
            ->where('due_date', '<=', now()->toDateString())
            ->whereHas('loan', fn ($q) => $q->when($branchId, fn ($q2) => $q2->where('branch_id', $branchId)))
            ->selectRaw('due_date as day, SUM(total_amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return $days->map(function (string $day) use ($savingsByDay, $loanByDay, $installmentByDay) {
            $simpanan = (float) ($savingsByDay[$day] ?? 0);
            $pinjaman = (float) ($loanByDay[$day] ?? 0);
            $angsuran = (float) ($installmentByDay[$day] ?? 0);

            return [
                'date' => $day,
                'label' => Carbon::parse($day)->translatedFormat('d/m'),
                'simpanan' => $simpanan,
                'pinjaman' => $pinjaman,
                'angsuran' => $angsuran,
                'total' => $simpanan + $pinjaman + $angsuran,
            ];
        })->all();
    }

    /**
     * SHU berjalan = Pendapatan − Beban, dihitung dari saldo bersih
     * journal_lines per tipe akun (bukan angka tersimpan terpisah), PLUS
     * kontribusi migrasi SMIK dari OpeningBalanceCoa (migratedNetFor()) —
     * sama seperti GeneralLedgerService::balanceFor(), dan disengaja untuk
     * alasan yang sama: status batch (draft/locked) tidak boleh membuat
     * SHU Berjalan tampak nol untuk cabang yang migrasinya belum dikunci.
     * Kedua sumber saling eksklusif (jurnal pembukaan migrasi dikeluarkan
     * dari sum journal_lines di sini) supaya batch yang sudah dikunci
     * tidak terhitung dua kali.
     *
     * Ini BEDA dengan Laba Rugi per PERIODE (FinancialReportEngine::
     * periodMovement(), dipakai tab Laba Rugi di Laporan Keuangan): angka
     * itu sengaja TIDAK memuat migrasi, karena representasinya adalah
     * "mutasi Pendapatan/Beban dalam rentang tanggal yang dipilih user",
     * bukan akumulasi sejak awal tahun buku — migrasi cutoff-nya nyaris
     * selalu jatuh SEBELUM periode yang dilihat, jadi memang tidak relevan
     * di situ. shu_running di sini justru sebaliknya: akumulasi SEJAK AWAL
     * ledger (yang dimulai dari migrasi) s.d. sekarang, sama persis dengan
     * FinancialReportEngine::shuBerjalan() yang mengisi baris SHU Tahun
     * Berjalan di Neraca — kedua tempat ini HARUS selalu konsisten.
     *
     * Pendapatan dihitung (kredit − debit); Beban dihitung (debit − kredit)
     * — ini otomatis benar walau ada akun kontra (mis. Retur Penjualan yang
     * bersaldo normal debit di dalam kelompok PENDAPATAN).
     *
     * @return array{pendapatan: float, beban: float, shu: float}
     */
    private function shuBreakdown(?int $branchId): array
    {
        $baseQuery = fn () => JournalLine::query()
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_lines.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->when($branchId, fn ($q) => $q->where('journal_entries.branch_id', $branchId))
            ->where(function ($q) {
                $q->whereNull('journal_entries.source_type')
                    ->orWhere('journal_entries.source_type', '!=', OpeningBalanceBatch::class);
            });

        $pendapatan = (float) $baseQuery()
            ->where('chart_of_accounts.type', 'PENDAPATAN')
            ->selectRaw('COALESCE(SUM(journal_lines.credit - journal_lines.debit), 0) as net')
            ->value('net');
        $pendapatan += $this->migratedNetFor('PENDAPATAN', $branchId);

        $beban = (float) $baseQuery()
            ->where('chart_of_accounts.type', 'BEBAN')
            ->selectRaw('COALESCE(SUM(journal_lines.debit - journal_lines.credit), 0) as net')
            ->value('net');
        $beban += $this->migratedNetFor('BEBAN', $branchId);

        return ['pendapatan' => $pendapatan, 'beban' => $beban, 'shu' => round($pendapatan - $beban, 2)];
    }

    /**
     * Kontribusi saldo awal migrasi SMIK untuk SHU Berjalan, dari
     * OpeningBalanceCoa — dijumlah untuk SEMUA OpeningBalanceBatch (draft
     * maupun locked, lihat catatan di shuBreakdown() di atas dan
     * GeneralLedgerService::openingBalanceFor() untuk penjelasan
     * lengkapnya).
     */
    private function migratedNetFor(string $type, ?int $branchId): float
    {
        $batchIds = OpeningBalanceBatch::query()
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->pluck('id');

        if ($batchIds->isEmpty()) {
            return 0.0;
        }

        $sums = OpeningBalanceCoa::query()
            ->whereIn('opening_balance_batch_id', $batchIds)
            ->whereHas('account', fn ($q) => $q->where('type', $type))
            ->selectRaw("COALESCE(SUM(CASE WHEN position = 'kredit' THEN amount ELSE 0 END), 0) as total_credit, COALESCE(SUM(CASE WHEN position = 'debit' THEN amount ELSE 0 END), 0) as total_debit")
            ->first();

        return $type === 'PENDAPATAN'
            ? (float) $sums->total_credit - (float) $sums->total_debit
            : (float) $sums->total_debit - (float) $sums->total_credit;
    }
}
