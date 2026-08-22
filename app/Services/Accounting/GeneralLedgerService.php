<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceCoa;
use App\Models\SavingsAccount;
use Illuminate\Support\Collection;

/**
 * Buku Besar (General Ledger, PRD §2.3, Task 5.2) — read-model over
 * journal_lines, scoped per cabang or konsolidasi. Balances are always
 * computed here, never cached (same principle as every other ledger read
 * in this app).
 *
 * balanceFor() menggabungkan DUA sumber, bukan cuma journal_lines: mutasi
 * ledger biasa, PLUS saldo awal migrasi SMIK dari OpeningBalanceCoa (lewat
 * openingBalanceFor()) untuk setiap OpeningBalanceBatch yang cutoff_date-nya
 * sudah lewat. Ini disengaja, bukan sekadar penyederhanaan — status batch
 * (draft/locked) TIDAK relevan untuk apakah saldo migrasinya kelihatan di
 * sini: OpeningBalanceLockService::lock() baru menjurnal (posting ke
 * journal_lines) saat batch dikunci, jadi kalau balanceFor() hanya baca
 * journal_lines, akun yang belum ada mutasi baru sejak migrasi akan
 * tampak bersaldo nol padahal seharusnya menampilkan saldo migrasi —
 * persis keluhan "kalau transaksi kosong, saldo yang tampil harusnya
 * saldo awal" pada Neraca/Neraca Saldo bulanan.
 *
 * Supaya batch yang SUDAH dikunci tidak terhitung dua kali (sekali dari
 * jurnal pembukaannya, sekali lagi dari openingBalanceFor()), jurnal
 * pembukaan migrasi (source_type=OpeningBalanceBatch, lihat
 * OpeningBalanceLockService::postOpeningJournal()) dikeluarkan dari sum
 * journal_lines di sini — dua sumber itu saling eksklusif per definisi,
 * bukan cuma kebetulan tidak tumpang tindih.
 */
class GeneralLedgerService
{
    /**
     * Kartu buku besar per akun: saldo awal periode + mutasi dalam periode +
     * running balance yang bergerak dari saldo awal. Baris pertama selalu
     * saldo awal sintetik (is_opening=true, debit/credit=0) supaya cetakan
     * dan layar konsisten dengan konvensi buku besar — tanpa itu akun
     * kas/bank yang punya mutasi sebelum periode tampak seolah mulai
     * dari nol.
     *
     * Saldo awal dihitung ulang tiap panggilan lewat balanceFor() (ledger +
     * saldo migrasi, lihat catatan kelas di atas, per periodStart - 1
     * hari), sama seperti prinsip "balances always computed, never
     * cached" di kelas ini.
     *
     * @return Collection<int, array{date: string, description: string, debit: string, credit: string, running_balance: string, is_opening: bool}>
     */
    public function linesFor(ChartOfAccount $account, ?int $branchId, string $periodStart, string $periodEnd): Collection
    {
        $openingAsOf = \Illuminate\Support\Carbon::parse($periodStart)->subDay()->toDateString();
        $opening = $this->balanceFor($account, $branchId, $openingAsOf);

        $lines = JournalLine::query()
            ->where('chart_of_account_id', $account->id)
            ->whereHas('journalEntry', function ($query) use ($branchId, $periodStart, $periodEnd) {
                $query->whereBetween('entry_date', [$periodStart, $periodEnd]);

                if ($branchId !== null) {
                    $query->where('branch_id', $branchId);
                }
            })
            ->with('journalEntry')
            ->get()
            ->sortBy([
                fn ($a, $b) => $a->journalEntry->entry_date <=> $b->journalEntry->entry_date,
                fn ($a, $b) => $a->id <=> $b->id,
            ])
            ->values();

        $running = $opening;
        $isDebitNormal = $account->normal_balance === 'DEBIT';

        $result = collect([[
            'date' => $periodStart,
            'description' => 'Saldo Awal Periode',
            'debit' => '0.00',
            'credit' => '0.00',
            'running_balance' => $opening,
            'is_opening' => true,
        ]]);

        foreach ($lines as $line) {
            $delta = $isDebitNormal
                ? bcsub((string) $line->debit, (string) $line->credit, 2)
                : bcsub((string) $line->credit, (string) $line->debit, 2);

            $running = bcadd($running, $delta, 2);

            $result->push([
                'date' => $line->journalEntry->entry_date->toDateString(),
                'description' => $line->journalEntry->description,
                'debit' => (string) $line->debit,
                'credit' => (string) $line->credit,
                'running_balance' => $running,
                'is_opening' => false,
            ]);
        }

        return $result;
    }

    public function balanceFor(ChartOfAccount $account, ?int $branchId, string $asOfDate): string
    {
        $isDebitNormal = $account->normal_balance === 'DEBIT';

        $sums = JournalLine::query()
            ->where('chart_of_account_id', $account->id)
            ->whereHas('journalEntry', function ($query) use ($branchId, $asOfDate) {
                $query->whereDate('entry_date', '<=', $asOfDate);

                // Jurnal pembukaan migrasi dikeluarkan dari sini — lihat
                // catatan kelas di atas. Kontribusinya ditambahkan
                // eksplisit lewat openingBalanceFor() di bawah.
                $query->where(function ($q) {
                    $q->whereNull('source_type')->orWhere('source_type', '!=', OpeningBalanceBatch::class);
                });

                if ($branchId !== null) {
                    $query->where('branch_id', $branchId);
                }
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $ledgerBalance = $isDebitNormal
            ? bcsub((string) $sums->total_debit, (string) $sums->total_credit, 2)
            : bcsub((string) $sums->total_credit, (string) $sums->total_debit, 2);

        return bcadd($ledgerBalance, $this->openingBalanceFor($account, $branchId, $asOfDate), 2);
    }

    /**
     * Kontribusi saldo awal migrasi SMIK untuk satu akun, dari
     * OpeningBalanceCoa — dihitung untuk SEMUA OpeningBalanceBatch yang
     * cutoff_date-nya <= $asOfDate (draft maupun locked, lihat catatan
     * kelas), supaya melihat saldo per tanggal SEBELUM cutoff tidak ikut
     * memasukkan migrasi yang secara kronologis belum "terjadi".
     *
     * BranchScope pada OpeningBalanceBatch (trait BelongsToBranch) sudah
     * mempersempit ke cabang yang boleh diakses user — sama seperti
     * journal_lines di balanceFor() di atas — jadi $branchId=null untuk
     * user yang scope-nya dibatasi tetap otomatis terbatas, bukan bocor
     * ke cabang lain.
     */
    private function openingBalanceFor(ChartOfAccount $account, ?int $branchId, string $asOfDate): string
    {
        $batchIds = OpeningBalanceBatch::query()
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('cutoff_date', '<=', $asOfDate)
            ->pluck('id');

        if ($batchIds->isEmpty()) {
            return '0.00';
        }

        $isDebitNormal = $account->normal_balance === 'DEBIT';

        $sums = OpeningBalanceCoa::query()
            ->where('chart_of_account_id', $account->id)
            ->whereIn('opening_balance_batch_id', $batchIds)
            ->selectRaw("COALESCE(SUM(CASE WHEN position = 'debit' THEN amount ELSE 0 END), 0) as total_debit, COALESCE(SUM(CASE WHEN position = 'kredit' THEN amount ELSE 0 END), 0) as total_credit")
            ->first();

        return $isDebitNormal
            ? bcsub((string) $sums->total_debit, (string) $sums->total_credit, 2)
            : bcsub((string) $sums->total_credit, (string) $sums->total_debit, 2);
    }

    /**
     * LED-09 — rekonsiliasi saldo akun Kewajiban Simpanan di buku besar vs
     * jumlah `savings_accounts.balance` (rincian per anggota). Ini adalah
     * satu implementasi konkret dari prinsip rekonsiliasi yang berlaku
     * sama untuk modul lain (pinjaman, UPF, dst).
     *
     * @return array{ledger_balance: string, detail_total: string, matches: bool}
     */
    public function reconcileSavingsLiability(ChartOfAccount $liabilityAccount, ?int $branchId): array
    {
        $ledgerBalance = $this->balanceFor($liabilityAccount, $branchId, now()->toDateString());

        $detailTotal = SavingsAccount::query()
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereHas('savingsProduct', fn ($query) => $query->where('coa_liability_account_id', $liabilityAccount->id))
            ->sum('balance');

        $detailTotal = (string) $detailTotal;

        return [
            'ledger_balance' => $ledgerBalance,
            'detail_total' => $detailTotal,
            'matches' => bccomp($ledgerBalance, $detailTotal, 2) === 0,
        ];
    }
}
