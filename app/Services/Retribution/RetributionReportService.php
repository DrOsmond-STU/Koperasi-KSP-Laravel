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
     * Rekap pendapatan UPF per periode (portrait) — dipakai
     * RetributionController::printRekap() untuk kartu "Rekap Pendapatan UPF
     * (Portrait)" di tab Laporan halaman Retribusi (UPF). Total & breakdown
     * SELALU mengecualikan transaksi yang dibatalkan (cancelled_at bukan
     * null) — beda dengan retribusiUpf() di atas, yang tidak menyaring
     * cancelled_at karena baris yang dibatalkan tetap perlu tampil di sana
     * sebagai jejak audit per transaksi.
     *
     * Breakdown mendaftar SEMUA jenis retribusi aktif (bukan cuma yang
     * kebagian transaksi pada periode ini) supaya pengurus melihat cakupan
     * lengkap tarif yang berlaku — jenis yang tidak bertransaksi tampil
     * dengan angka nol.
     *
     * @param  array{branch_id?: int|null, period_start: string, period_end: string}  $filters
     * @return array{period_start: string, period_end: string, transaction_count: int, transaction_count_umum: int, transaction_count_anggota: int, total_cash_in: float, breakdown: array<int, array{name: string, count: int, total: float}>}
     */
    public function rekapPendapatan(array $filters): array
    {
        $branchId = $filters['branch_id'] ?? null;

        $transactionTotals = RetributionTransaction::query()
            ->whereNull('cancelled_at')
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->where('transaction_date', '>=', $filters['period_start'])
            ->where('transaction_date', '<=', $filters['period_end'])
            ->selectRaw("COUNT(*) as total_count, SUM(CASE WHEN payer_type = 'umum' THEN 1 ELSE 0 END) as umum_count, SUM(CASE WHEN payer_type = 'anggota' THEN 1 ELSE 0 END) as anggota_count, COALESCE(SUM(total_amount), 0) as total_cash_in")
            ->first();

        $lineSummaryByTypeId = RetributionTransactionLine::query()
            ->join('retribution_transactions', 'retribution_transactions.id', '=', 'retribution_transaction_lines.retribution_transaction_id')
            ->whereNull('retribution_transactions.cancelled_at')
            ->when($branchId, fn ($q, $id) => $q->where('retribution_transactions.branch_id', $id))
            ->where('retribution_transactions.transaction_date', '>=', $filters['period_start'])
            ->where('retribution_transactions.transaction_date', '<=', $filters['period_end'])
            ->selectRaw('retribution_transaction_lines.retribution_type_id, COUNT(*) as line_count, COALESCE(SUM(retribution_transaction_lines.amount), 0) as line_total')
            ->groupBy('retribution_transaction_lines.retribution_type_id')
            ->get()
            ->keyBy('retribution_type_id');

        $breakdown = RetributionType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (RetributionType $type) use ($lineSummaryByTypeId) {
                $summary = $lineSummaryByTypeId->get($type->id);

                return [
                    'name' => $type->name,
                    'count' => (int) ($summary->line_count ?? 0),
                    'total' => (float) ($summary->line_total ?? 0),
                ];
            })
            ->values()
            ->all();

        return [
            'period_start' => $filters['period_start'],
            'period_end' => $filters['period_end'],
            'transaction_count' => (int) $transactionTotals->total_count,
            'transaction_count_umum' => (int) $transactionTotals->umum_count,
            'transaction_count_anggota' => (int) $transactionTotals->anggota_count,
            'total_cash_in' => (float) $transactionTotals->total_cash_in,
            'breakdown' => $breakdown,
        ];
    }
}
