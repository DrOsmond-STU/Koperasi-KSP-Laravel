<?php

namespace App\Services\Dashboard;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\TellerCashTransaction;

/**
 * Dashboard Kas/Bank (PRD §15) — saldo & mutasi diturunkan langsung dari
 * journal_lines pada akun kas/bank (kode 1101/1102/1110 di
 * SEED_CHART_OF_ACCOUNTS.md), bukan angka tersimpan terpisah (LED-09).
 *
 * v2 (Task 2.6): breakdown kas masuk/keluar hari ini per Kategori Kas
 * Teller (kategori dinamis, PRD §11) ditambahkan di sini setelah modul Kas
 * Teller ada. Proyeksi arus kas jangka pendek masih menyusul (butuh
 * histori transaksi lebih panjang untuk proyeksi yang berarti).
 */
class CashBankDashboardService
{
    private const CASH_BANK_CODES = ['1101', '1102', '1110'];

    public function summary(?int $branchId = null): array
    {
        $accounts = ChartOfAccount::query()->whereIn('code', self::CASH_BANK_CODES)->get();
        $accountIds = $accounts->pluck('id');

        $balances = $accounts->map(function ($account) use ($branchId) {
            $balance = (float) JournalLine::query()
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_lines.chart_of_account_id', $account->id)
                ->when($branchId, fn ($q) => $q->where('journal_entries.branch_id', $branchId))
                ->selectRaw('COALESCE(SUM(journal_lines.debit - journal_lines.credit), 0) as net')
                ->value('net');

            return ['account' => $account, 'balance' => $balance];
        });

        $todayBase = fn () => JournalLine::query()
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_lines.chart_of_account_id', $accountIds)
            ->whereDate('journal_entries.entry_date', now()->toDateString())
            ->when($branchId, fn ($q) => $q->where('journal_entries.branch_id', $branchId));

        $todayIn = (float) $todayBase()->sum('journal_lines.debit');
        $todayOut = (float) $todayBase()->sum('journal_lines.credit');

        $recentEntries = JournalEntry::query()
            ->whereHas('lines', fn ($q) => $q->whereIn('chart_of_account_id', $accountIds))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['lines.account'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return [
            'balances' => $balances,
            'total_balance' => (float) $balances->sum('balance'),
            'today_in' => $todayIn,
            'today_out' => $todayOut,
            'recent_entries' => $recentEntries,
            'category_breakdown' => $this->categoryBreakdown($branchId),
        ];
    }

    /**
     * @return array<int, array{category_name: string, type: string, total: float}>
     */
    private function categoryBreakdown(?int $branchId): array
    {
        return TellerCashTransaction::query()
            ->join('cash_categories', 'teller_cash_transactions.cash_category_id', '=', 'cash_categories.id')
            ->whereDate('teller_cash_transactions.created_at', now()->toDateString())
            ->when($branchId, fn ($q) => $q->where('teller_cash_transactions.branch_id', $branchId))
            ->selectRaw('cash_categories.name as category_name, cash_categories.type as type, SUM(teller_cash_transactions.amount) as total')
            ->groupBy('cash_categories.name', 'cash_categories.type')
            ->get()
            ->map(fn ($row) => [
                'category_name' => $row->category_name,
                'type' => $row->type,
                'total' => (float) $row->total,
            ])
            ->all();
    }
}
