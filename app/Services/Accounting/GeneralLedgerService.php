<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Models\SavingsAccount;
use Illuminate\Support\Collection;

/**
 * Buku Besar (General Ledger, PRD §2.3, Task 5.2) — read-model over
 * journal_lines, scoped per cabang or konsolidasi. Balances are always
 * computed here, never cached (same principle as every other ledger read
 * in this app).
 */
class GeneralLedgerService
{
    /**
     * @return Collection<int, array{date: string, description: string, debit: string, credit: string, running_balance: string}>
     */
    public function linesFor(ChartOfAccount $account, ?int $branchId, string $periodStart, string $periodEnd): Collection
    {
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

        $running = '0.00';
        $isDebitNormal = $account->normal_balance === 'DEBIT';

        return $lines->map(function (JournalLine $line) use (&$running, $isDebitNormal) {
            $delta = $isDebitNormal
                ? bcsub((string) $line->debit, (string) $line->credit, 2)
                : bcsub((string) $line->credit, (string) $line->debit, 2);

            $running = bcadd($running, $delta, 2);

            return [
                'date' => $line->journalEntry->entry_date->toDateString(),
                'description' => $line->journalEntry->description,
                'debit' => (string) $line->debit,
                'credit' => (string) $line->credit,
                'running_balance' => $running,
            ];
        });
    }

    public function balanceFor(ChartOfAccount $account, ?int $branchId, string $asOfDate): string
    {
        $isDebitNormal = $account->normal_balance === 'DEBIT';

        $sums = JournalLine::query()
            ->where('chart_of_account_id', $account->id)
            ->whereHas('journalEntry', function ($query) use ($branchId, $asOfDate) {
                $query->whereDate('entry_date', '<=', $asOfDate);

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
