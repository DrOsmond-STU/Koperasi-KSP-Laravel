<?php

namespace App\Services\Retribution;

use App\Models\RetributionTransaction;
use App\Models\RetributionTransactionLine;
use App\Models\RetributionType;
use Illuminate\Support\Collection;

/**
 * Sumber baris untuk laporan dinamis "retribusi_upf" (dipanggil dari
 * ReportBuilderService::fetchRawRows()). Mengembalikan array asosiatif
 * polos per baris — bukan Eloquent model — karena setiap baris punya kolom
 * dinamis `retribusi_line_{id}` (satu per jenis retribusi aktif saat
 * request), yang tidak bisa dimodelkan sebagai atribut Eloquent tetap.
 */
class RetributionReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function retribusiUpf(array $filters): Collection
    {
        $activeTypeIds = RetributionType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->pluck('id');

        $transactions = RetributionTransaction::query()
            ->with(['lines', 'branch', 'creator', 'member'])
            ->when($filters['branch_id'] ?? null, fn ($q, $branchId) => $q->where('branch_id', $branchId))
            ->when($filters['period_start'] ?? null, fn ($q, $date) => $q->where('transaction_date', '>=', $date))
            ->when($filters['period_end'] ?? null, fn ($q, $date) => $q->where('transaction_date', '<=', $date))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        return $transactions->map(function (RetributionTransaction $transaction) use ($activeTypeIds) {
            $linesByTypeId = $transaction->lines->keyBy('retribution_type_id');

            $row = [
                'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                'transaction_number' => $transaction->transaction_number,
                'payer_name' => $transaction->payer_name,
                'member_number' => $transaction->member?->member_number,
                'total_amount' => (float) $transaction->total_amount,
                'payment_method' => $transaction->payment_method,
                'branch_name' => $transaction->branch->name,
                'created_by_name' => $transaction->creator->name,
                'description' => $transaction->description,
            ];

            foreach ($activeTypeIds as $typeId) {
                $row["retribusi_line_{$typeId}"] = (float) ($linesByTypeId[$typeId]->amount ?? 0);
            }

            return $row;
        });
    }

    /**
     * Rekap ringkas periode: total kas masuk + breakdown per jenis
     * retribusi (nama, jumlah baris terpakai, total nilai) — dipakai oleh
     * cetakan portrait "Rekap Pendapatan UPF" (periode harian s/d bulanan).
     *
     * Sumbernya `retribution_transaction_lines` (yang di-snapshot per
     * transaksi) di-join ke header supaya bisa difilter branch/tanggal;
     * transaksi yang dibatalkan (`cancelled_at IS NOT NULL`) DIKELUARKAN
     * — jumlahnya sudah tercermin di jurnal pembalik dan tidak lagi
     * jadi pendapatan.
     *
     * @param  array{branch_id?: int|null, period_start: string, period_end: string}  $filters
     * @return array{
     *     period_start: string,
     *     period_end: string,
     *     branch_id: int|null,
     *     transaction_count: int,
     *     transaction_count_umum: int,
     *     transaction_count_anggota: int,
     *     total_cash_in: float,
     *     breakdown: array<int, array{name: string, count: int, total: float}>,
     * }
     */
    public function rekapPendapatan(array $filters): array
    {
        $branchId = $filters['branch_id'] ?? null;
        $start = $filters['period_start'];
        $end = $filters['period_end'];

        $baseTx = RetributionTransaction::query()
            ->whereNull('cancelled_at')
            ->where('transaction_date', '>=', $start)
            ->where('transaction_date', '<=', $end)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $totalCashIn = (float) (clone $baseTx)->sum('total_amount');
        $transactionCount = (clone $baseTx)->count();
        $transactionCountUmum = (clone $baseTx)->where('payer_type', 'umum')->count();
        $transactionCountAnggota = (clone $baseTx)->where('payer_type', 'anggota')->count();

        $breakdown = RetributionTransactionLine::query()
            ->join('retribution_transactions', 'retribution_transactions.id', '=', 'retribution_transaction_lines.retribution_transaction_id')
            ->whereNull('retribution_transactions.cancelled_at')
            ->where('retribution_transactions.transaction_date', '>=', $start)
            ->where('retribution_transactions.transaction_date', '<=', $end)
            ->when($branchId, fn ($q) => $q->where('retribution_transactions.branch_id', $branchId))
            ->selectRaw('retribution_transaction_lines.retribution_type_name as name, COUNT(*) as line_count, SUM(retribution_transaction_lines.amount) as total')
            ->groupBy('retribution_transaction_lines.retribution_type_name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'count' => (int) $row->line_count,
                'total' => (float) $row->total,
            ])
            ->all();

        return [
            'period_start' => $start,
            'period_end' => $end,
            'branch_id' => $branchId,
            'transaction_count' => $transactionCount,
            'transaction_count_umum' => $transactionCountUmum,
            'transaction_count_anggota' => $transactionCountAnggota,
            'total_cash_in' => $totalCashIn,
            'breakdown' => $breakdown,
        ];
    }
}
