<?php

namespace App\Services\Retribution;

use App\Models\RetributionTransaction;
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
}
