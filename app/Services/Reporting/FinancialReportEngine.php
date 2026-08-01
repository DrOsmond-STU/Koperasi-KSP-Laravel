<?php

namespace App\Services\Reporting;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Services\Accounting\GeneralLedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Mesin Laporan Keuangan on-demand dari ledger (PRD §2.3/§17, Task 5.3) —
 * Neraca, Laba Rugi, Arus Kas, CALK. Every figure is computed fresh from
 * journal_lines (LED-09 single source of truth) — EXCEPT for a single
 * branch's report over a period that's already closed (`AccountingPeriod
 * status=closed`), which JournalEngine::assertPeriodOpen() guarantees can
 * never receive another posting — so it's cached forever (08_TASK_INSTRUCTION
 * 6.2). Open periods and consolidated (`$branchId === null`) reports are
 * never cached — a consolidated view spans every branch's periods, and
 * confirming all of them are closed isn't worth the complexity for a
 * report that's requested rarely (RAT/year-end), not on the hot path.
 *
 * `$basis` ('sak_ep'|'sak_emkm') only changes report titles/labels — the
 * underlying chart of accounts and ledger are shared across both bases
 * (SEED_CHART_OF_ACCOUNTS.md: "kompatibel untuk keduanya"), so the figures
 * are always identical regardless of basis (RPT-04).
 */
class FinancialReportEngine
{
    private const RAK_ACCOUNT_CODE = '1132';

    private const CASH_BANK_CODES = ['1101', '1102', '1110'];

    private const SHU_BERJALAN_ACCOUNT_CODE = '3150';

    public function __construct(private readonly GeneralLedgerService $ledger) {}

    /**
     * Neraca (Balance Sheet) — RPT-01/RPT-05.
     */
    public function neraca(?int $branchId, string $asOfDate, string $basis = 'sak_ep'): array
    {
        if ($branchId !== null && $this->isDateInClosedPeriod($branchId, $asOfDate)) {
            return Cache::rememberForever(
                "financial_report:neraca:{$branchId}:{$asOfDate}:{$basis}",
                fn () => $this->computeNeraca($branchId, $asOfDate, $basis),
            );
        }

        return $this->computeNeraca($branchId, $asOfDate, $basis);
    }

    private function computeNeraca(?int $branchId, string $asOfDate, string $basis): array
    {
        $accounts = ChartOfAccount::query()
            ->where('statement', 'NERACA')
            ->where('is_postable', true)
            ->orderBy('code')
            ->get();

        $rows = $accounts->map(function (ChartOfAccount $account) use ($branchId, $asOfDate) {
            $isRak = $account->code === self::RAK_ACCOUNT_CODE;
            $eliminated = $branchId === null && $isRak;

            if ($eliminated) {
                $balance = '0.00';
            } elseif ($account->code === self::SHU_BERJALAN_ACCOUNT_CODE) {
                // Belum ada mekanisme tutup buku (yang menjurnal Pendapatan/
                // Beban ke akun ini) — jadi saldonya dihitung LIVE dari
                // laba/rugi kumulatif sejak awal ledger, bukan dibaca dari
                // jurnal (yang akan selalu 0 sampai tutup buku pertama
                // dijalankan). Tanpa ini Neraca tidak akan pernah seimbang
                // begitu ada transaksi pendapatan/beban apa pun, karena
                // sisi Aset berubah tapi sisi Ekuitas tidak — bukan
                // kesalahan data, tapi memang begitu cara kerja neraca
                // interim sebelum tutup buku.
                $balance = $this->shuBerjalan($branchId, $asOfDate);
            } else {
                $balance = $this->ledger->balanceFor($account, $branchId, $asOfDate);
            }

            return [
                'account' => $account,
                'balance' => $balance,
                'eliminated' => $eliminated,
            ];
        });

        $totalAset = $this->sumByType($rows, 'ASET');
        $totalLiabilitas = $this->sumByType($rows, 'LIABILITAS');
        $totalEkuitas = $this->sumByType($rows, 'EKUITAS');

        return [
            'basis' => $basis,
            'as_of_date' => $asOfDate,
            'is_consolidated' => $branchId === null,
            'rows' => $rows,
            'total_aset' => $totalAset,
            'total_liabilitas' => $totalLiabilitas,
            'total_ekuitas' => $totalEkuitas,
            'is_balanced' => bccomp($totalAset, bcadd($totalLiabilitas, $totalEkuitas, 2), 2) === 0,
            'has_data' => bccomp($totalAset, '0', 2) !== 0 || bccomp($totalLiabilitas, '0', 2) !== 0,
        ];
    }

    /**
     * Laba Rugi / Perhitungan Hasil Usaha — RPT-02.
     */
    public function labaRugi(?int $branchId, string $periodStart, string $periodEnd, string $basis = 'sak_ep'): array
    {
        if ($branchId !== null && $this->isRangeInClosedPeriod($branchId, $periodStart, $periodEnd)) {
            return Cache::rememberForever(
                "financial_report:laba_rugi:{$branchId}:{$periodStart}:{$periodEnd}:{$basis}",
                fn () => $this->computeLabaRugi($branchId, $periodStart, $periodEnd, $basis),
            );
        }

        return $this->computeLabaRugi($branchId, $periodStart, $periodEnd, $basis);
    }

    private function computeLabaRugi(?int $branchId, string $periodStart, string $periodEnd, string $basis): array
    {
        $accounts = ChartOfAccount::query()
            ->where('statement', 'LABA_RUGI')
            ->where('is_postable', true)
            ->orderBy('code')
            ->get();

        $rows = $accounts->map(fn (ChartOfAccount $account) => [
            'account' => $account,
            'amount' => $this->periodMovement($account, $branchId, $periodStart, $periodEnd),
        ]);

        $totalPendapatan = $this->sumByType($rows, 'PENDAPATAN', 'amount');
        $totalBeban = $this->sumByType($rows, 'BEBAN', 'amount');
        $shu = bcsub($totalPendapatan, $totalBeban, 2);

        return [
            'basis' => $basis,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'is_consolidated' => $branchId === null,
            'rows' => $rows,
            'total_pendapatan' => $totalPendapatan,
            'total_beban' => $totalBeban,
            'shu' => $shu,
            'has_data' => $rows->contains(fn (array $row) => bccomp($row['amount'], '0', 2) !== 0),
        ];
    }

    /**
     * Arus Kas — RPT-03. Direct-method summary (masuk/keluar/bersih)
     * reconciled against opening/closing ledger balances of the cash/bank
     * accounts (LED-09 style consistency check).
     */
    public function arusKas(?int $branchId, string $periodStart, string $periodEnd): array
    {
        if ($branchId !== null && $this->isRangeInClosedPeriod($branchId, $periodStart, $periodEnd)) {
            return Cache::rememberForever(
                "financial_report:arus_kas:{$branchId}:{$periodStart}:{$periodEnd}",
                fn () => $this->computeArusKas($branchId, $periodStart, $periodEnd),
            );
        }

        return $this->computeArusKas($branchId, $periodStart, $periodEnd);
    }

    private function computeArusKas(?int $branchId, string $periodStart, string $periodEnd): array
    {
        $cashAccounts = ChartOfAccount::query()->whereIn('code', self::CASH_BANK_CODES)->get();
        $dayBeforeStart = Carbon::parse($periodStart)->subDay()->toDateString();

        $opening = '0.00';
        $closing = '0.00';
        $totalMasuk = '0.00';
        $totalKeluar = '0.00';

        foreach ($cashAccounts as $account) {
            $opening = bcadd($opening, $this->ledger->balanceFor($account, $branchId, $dayBeforeStart), 2);
            $closing = bcadd($closing, $this->ledger->balanceFor($account, $branchId, $periodEnd), 2);

            $sums = JournalLine::query()
                ->where('chart_of_account_id', $account->id)
                ->whereHas('journalEntry', function ($query) use ($branchId, $periodStart, $periodEnd) {
                    $query->whereBetween('entry_date', [$periodStart, $periodEnd]);

                    if ($branchId !== null) {
                        $query->where('branch_id', $branchId);
                    }
                })
                ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
                ->first();

            $totalMasuk = bcadd($totalMasuk, (string) $sums->total_debit, 2);
            $totalKeluar = bcadd($totalKeluar, (string) $sums->total_credit, 2);
        }

        $netMovement = bcsub($totalMasuk, $totalKeluar, 2);

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'is_consolidated' => $branchId === null,
            'opening_balance' => $opening,
            'closing_balance' => $closing,
            'total_masuk' => $totalMasuk,
            'total_keluar' => $totalKeluar,
            'net_movement' => $netMovement,
            'is_consistent' => bccomp(bcadd($opening, $netMovement, 2), $closing, 2) === 0,
            'has_data' => bccomp($totalMasuk, '0', 2) !== 0 || bccomp($totalKeluar, '0', 2) !== 0,
        ];
    }

    /**
     * CALK (Catatan atas Laporan Keuangan) — a structured summary of
     * balances per akun group plus the standard accounting-policy notes,
     * not a full free-text disclosure document.
     */
    public function calk(?int $branchId, string $asOfDate, string $basis = 'sak_ep'): array
    {
        if ($branchId !== null && $this->isDateInClosedPeriod($branchId, $asOfDate)) {
            return Cache::rememberForever(
                "financial_report:calk:{$branchId}:{$asOfDate}:{$basis}",
                fn () => $this->computeCalk($branchId, $asOfDate, $basis),
            );
        }

        return $this->computeCalk($branchId, $asOfDate, $basis);
    }

    private function computeCalk(?int $branchId, string $asOfDate, string $basis): array
    {
        $accounts = ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get();

        $groups = $accounts
            ->map(fn (ChartOfAccount $account) => [
                'account' => $account,
                'balance' => $this->ledger->balanceFor($account, $branchId, $asOfDate),
            ])
            ->groupBy(fn (array $row) => $row['account']->group);

        return [
            'basis' => $basis,
            'as_of_date' => $asOfDate,
            'is_consolidated' => $branchId === null,
            'kebijakan_akuntansi' => $this->standardAccountingPolicies($basis),
            'groups' => $groups,
        ];
    }

    private function isDateInClosedPeriod(int $branchId, string $date): bool
    {
        return AccountingPeriod::query()
            ->where('branch_id', $branchId)
            ->where('status', 'closed')
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->exists();
    }

    private function isRangeInClosedPeriod(int $branchId, string $start, string $end): bool
    {
        return AccountingPeriod::query()
            ->where('branch_id', $branchId)
            ->where('status', 'closed')
            ->whereDate('period_start', '<=', $start)
            ->whereDate('period_end', '>=', $end)
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    private function standardAccountingPolicies(string $basis): array
    {
        $standardName = $basis === 'sak_emkm' ? 'SAK EMKM' : 'SAK EP';

        return [
            "Laporan keuangan disusun berdasarkan {$standardName} dan Permenkop UKM No. 2/2024.",
            'Dasar pengukuran adalah biaya historis, kecuali dinyatakan lain.',
            'Pengakuan pendapatan & beban menggunakan basis akrual.',
            'Penyusutan aktiva tetap dihitung sesuai metode yang ditetapkan per aset (Garis Lurus/Saldo Menurun).',
        ];
    }

    /**
     * Laba/rugi kumulatif sejak awal ledger s.d. $asOfDate — angka yang
     * sama persis dengan yang dipakai Dashboard Utama untuk "SHU Berjalan"
     * (MainDashboardService::shuBreakdown()), supaya kedua tempat selalu
     * konsisten.
     */
    private function shuBerjalan(?int $branchId, string $asOfDate): string
    {
        $pendapatan = ChartOfAccount::query()->where('type', 'PENDAPATAN')->where('is_postable', true)->get()
            ->reduce(fn (string $carry, ChartOfAccount $a) => bcadd($carry, $this->ledger->balanceFor($a, $branchId, $asOfDate), 2), '0.00');

        $beban = ChartOfAccount::query()->where('type', 'BEBAN')->where('is_postable', true)->get()
            ->reduce(fn (string $carry, ChartOfAccount $a) => bcadd($carry, $this->ledger->balanceFor($a, $branchId, $asOfDate), 2), '0.00');

        return bcsub($pendapatan, $beban, 2);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function sumByType(Collection $rows, string $type, string $valueKey = 'balance'): string
    {
        return $rows
            ->filter(fn (array $row) => $row['account']->type === $type)
            ->reduce(fn (string $carry, array $row) => bcadd($carry, $row[$valueKey], 2), '0.00');
    }

    private function periodMovement(ChartOfAccount $account, ?int $branchId, string $periodStart, string $periodEnd): string
    {
        $isDebitNormal = $account->normal_balance === 'DEBIT';

        $sums = JournalLine::query()
            ->where('chart_of_account_id', $account->id)
            ->whereHas('journalEntry', function ($query) use ($branchId, $periodStart, $periodEnd) {
                $query->whereBetween('entry_date', [$periodStart, $periodEnd]);

                if ($branchId !== null) {
                    $query->where('branch_id', $branchId);
                }
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        return $isDebitNormal
            ? bcsub((string) $sums->total_debit, (string) $sums->total_credit, 2)
            : bcsub((string) $sums->total_credit, (string) $sums->total_debit, 2);
    }
}
