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
            'daily_balance_trend' => $this->dailyBalanceTrend($branchId),
        ];
    }

    /**
     * Laporan Harian Kas/Bank (Fase Cetakan) — satu tanggal saja, dipakai
     * untuk cetakan. Meniru pola query summary()/dailyBalanceTrend() di
     * atas (opening balance = semua mutasi sebelum tanggal ini, lalu
     * mutasi hari itu sendiri) alih-alih jendela 14 hari tetap.
     *
     * `rows` is flattened to ONLY the cash/bank-account lines (a journal
     * entry's other, non-cash lines — e.g. the liability side of a savings
     * deposit — are deliberately excluded), each carrying its parent
     * entry's description/preparer, so the print view never has to
     * re-filter lines itself.
     *
     * @return array{date: string, opening_balance: float, closing_balance: float, total_in: float, total_out: float, rows: \Illuminate\Support\Collection}
     */
    public function dailyTransactions(string $date, ?int $branchId = null): array
    {
        $accountIds = ChartOfAccount::query()->whereIn('code', self::CASH_BANK_CODES)->pluck('id');

        $openingBalance = (float) JournalLine::query()
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_lines.chart_of_account_id', $accountIds)
            ->where('journal_entries.entry_date', '<', $date)
            ->when($branchId, fn ($q) => $q->where('journal_entries.branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(journal_lines.debit - journal_lines.credit), 0) as net')
            ->value('net');

        $entries = JournalEntry::query()
            ->whereHas('lines', fn ($q) => $q->whereIn('chart_of_account_id', $accountIds))
            ->whereDate('entry_date', $date)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['lines.account', 'createdBy'])
            ->orderBy('id')
            ->get();

        $rows = $entries->flatMap(fn (JournalEntry $entry) => $entry->lines
            ->filter(fn (JournalLine $line) => $accountIds->contains($line->chart_of_account_id))
            ->map(fn (JournalLine $line) => [
                'description' => $entry->description,
                'account_name' => $line->account->name,
                'created_by_name' => $entry->createdBy->name,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
            ]));

        return [
            'date' => $date,
            'opening_balance' => $openingBalance,
            'closing_balance' => $openingBalance + $rows->sum('debit') - $rows->sum('credit'),
            'total_in' => $rows->sum('debit'),
            'total_out' => $rows->sum('credit'),
            'rows' => $rows,
        ];
    }

    /**
     * Saldo kumulatif kas/bank per hari, 14 hari terakhir sampai hari ini
     * (untuk grafik tren pertumbuhan saldo). Dihitung dari saldo pembukaan
     * (semua mutasi SEBELUM jendela 14 hari) ditambah mutasi harian di
     * dalam jendela — 2 query, bukan 14 query terpisah per hari.
     *
     * @return array<int, array{date: string, label: string, balance: float, masuk: float, keluar: float}>
     */
    private function dailyBalanceTrend(?int $branchId): array
    {
        $accountIds = ChartOfAccount::query()->whereIn('code', self::CASH_BANK_CODES)->pluck('id');
        $start = now()->subDays(13)->startOfDay();
        $end = now()->endOfDay();

        $openingBalance = (float) JournalLine::query()
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_lines.chart_of_account_id', $accountIds)
            ->where('journal_entries.entry_date', '<', $start->toDateString())
            ->when($branchId, fn ($q) => $q->where('journal_entries.branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(journal_lines.debit - journal_lines.credit), 0) as net')
            ->value('net');

        $dailyMovement = JournalLine::query()
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_lines.chart_of_account_id', $accountIds)
            ->whereBetween('journal_entries.entry_date', [$start->toDateString(), $end->toDateString()])
            ->when($branchId, fn ($q) => $q->where('journal_entries.branch_id', $branchId))
            ->selectRaw('journal_entries.entry_date as entry_date, SUM(journal_lines.debit) as masuk, SUM(journal_lines.credit) as keluar')
            ->groupBy('journal_entries.entry_date')
            ->get()
            ->keyBy('entry_date');

        $trend = [];
        $running = $openingBalance;
        for ($i = 0; $i < 14; $i++) {
            $date = $start->copy()->addDays($i);
            $dateKey = $date->toDateString();
            $movement = $dailyMovement->get($dateKey);
            $masuk = (float) ($movement->masuk ?? 0);
            $keluar = (float) ($movement->keluar ?? 0);
            $running += $masuk - $keluar;
            $trend[] = [
                'date' => $dateKey,
                'label' => $date->translatedFormat('d/m'),
                'balance' => $running,
                'masuk' => $masuk,
                'keluar' => $keluar,
            ];
        }

        return $trend;
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
