<?php

namespace App\Services\Dashboard;

use App\Models\RetributionTransaction;
use App\Models\RetributionTransactionLine;
use Illuminate\Support\Carbon;

/**
 * Dashboard modul Retribusi (UPF) — KPI & grafik diturunkan langsung dari
 * retribution_transactions/retribution_transaction_lines, yang ditulis
 * atomik bersama journal_entries oleh RetributionService::record() dalam
 * satu DB transaction, jadi selalu konsisten dengan buku besar.
 */
class RetributionDashboardService
{
    public function summary(?int $branchId = null): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $base = fn () => RetributionTransaction::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $todayRevenue = (float) $base()->whereDate('transaction_date', $today)->sum('total_amount');
        $monthRevenue = (float) $base()->where('transaction_date', '>=', $monthStart)->sum('total_amount');
        $totalRevenue = (float) $base()->sum('total_amount');
        $todayTransactionCount = $base()->whereDate('transaction_date', $today)->count();
        $todayPayerCount = $base()->whereDate('transaction_date', $today)->distinct('payer_name')->count('payer_name');

        return [
            'today_revenue' => $todayRevenue,
            'month_revenue' => $monthRevenue,
            'total_revenue' => $totalRevenue,
            'today_transaction_count' => $todayTransactionCount,
            'today_payer_count' => $todayPayerCount,
            'seven_day_trend' => $this->sevenDayTrend($branchId),
            'breakdown_by_type' => $this->breakdownByType($branchId),
            'breakdown_by_type_today' => $this->breakdownByType($branchId, $today, $today),
            'breakdown_by_type_month' => $this->breakdownByType($branchId, $monthStart),
            'today_payers' => $this->todayPayers($branchId, $today),
        ];
    }

    /**
     * @return array<int, array{name: string, total: float}>
     */
    private function todayPayers(?int $branchId, string $today): array
    {
        return RetributionTransaction::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('transaction_date', $today)
            ->with('member')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn (RetributionTransaction $tx) => [
                'name' => $tx->payer_type === 'anggota' ? ($tx->member?->name ?? 'Anggota') : $tx->payer_name,
                'total' => (float) $tx->total_amount,
            ])
            ->all();
    }

    /**
     * @return array<int, array{date: string, label: string, total: float}>
     */
    private function sevenDayTrend(?int $branchId): array
    {
        $start = now()->subDays(6)->startOfDay();

        $totalsByDate = RetributionTransaction::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('transaction_date', '>=', $start->toDateString())
            ->selectRaw('transaction_date, SUM(total_amount) as total')
            ->groupBy('transaction_date')
            ->pluck('total', 'transaction_date');

        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $key = $date->toDateString();

            $days[] = [
                'date' => $key,
                'label' => $date->translatedFormat('d M'),
                'total' => (float) ($totalsByDate[$key] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * @return array<int, array{name: string, total: float}>
     */
    private function breakdownByType(?int $branchId, ?string $startDate = null, ?string $endDate = null): array
    {
        return RetributionTransactionLine::query()
            ->join('retribution_transactions', 'retribution_transactions.id', '=', 'retribution_transaction_lines.retribution_transaction_id')
            ->when($branchId, fn ($q) => $q->where('retribution_transactions.branch_id', $branchId))
            ->when($startDate, fn ($q) => $q->where('retribution_transactions.transaction_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('retribution_transactions.transaction_date', '<=', $endDate))
            ->selectRaw('retribution_transaction_lines.retribution_type_name as name, SUM(retribution_transaction_lines.amount) as total')
            ->groupBy('retribution_transaction_lines.retribution_type_name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'total' => (float) $row->total])
            ->all();
    }
}
