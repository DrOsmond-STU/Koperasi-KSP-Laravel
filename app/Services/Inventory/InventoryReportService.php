<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-model queries over `stock_ledger` for Laporan Persediaan (PRD §13.7,
 * Task 3.7). No module writes here — everything is derived from
 * StockLedgerEngine's append-only rows, same relationship JournalEngine
 * has with the financial reports built on top of journal_lines.
 */
class InventoryReportService
{
    /**
     * Laporan Saldo Persediaan: qty & nilai per barang, as of a given date.
     * $branchId = null means consolidated across all branches.
     *
     * @return Collection<int, array{product: Product, qty: string, value: string}>
     */
    public function saldoPersediaan(?int $branchId, ?string $asOfDate = null): Collection
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        $latestIds = DB::table('stock_ledger')
            ->selectRaw('MAX(id) as id')
            ->where('transaction_date', '<=', $asOfDate)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->groupBy('product_id', 'branch_id');

        $rows = DB::table('stock_ledger')
            ->joinSub($latestIds, 'latest', 'stock_ledger.id', '=', 'latest.id')
            ->select('stock_ledger.product_id', 'stock_ledger.branch_id', 'stock_ledger.running_qty', 'stock_ledger.running_avg_cost')
            ->get();

        $products = Product::query()->whereIn('id', $rows->pluck('product_id')->unique())->get()->keyBy('id');

        return $rows->groupBy('product_id')->map(function (Collection $branchRows, $productId) use ($products) {
            $qty = '0.0000';
            $value = '0.00';

            foreach ($branchRows as $row) {
                $qty = bcadd($qty, (string) $row->running_qty, 4);
                $value = bcadd($value, bcmul((string) $row->running_qty, (string) $row->running_avg_cost, 2), 2);
            }

            return [
                'product' => $products->get($productId),
                'qty' => $qty,
                'value' => $value,
            ];
        })->values();
    }

    /**
     * Laporan Kartu Persediaan — Ringkasan: stok awal, total masuk, total
     * keluar, stok akhir untuk satu barang dalam satu periode.
     *
     * @return array{opening_qty: string, total_in: string, total_out: string, closing_qty: string}
     */
    public function kartuRingkasan(int $productId, ?int $branchId, string $periodStart, string $periodEnd): array
    {
        $openingRow = DB::table('stock_ledger')
            ->where('product_id', $productId)
            ->where('transaction_date', '<', $periodStart)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->orderByDesc('id')
            ->first();

        $opening = $branchId !== null
            ? (string) ($openingRow->running_qty ?? '0.0000')
            : $this->consolidatedOpeningQty($productId, $periodStart);

        $mutations = DB::table('stock_ledger')
            ->where('product_id', $productId)
            ->whereBetween('transaction_date', [$periodStart, $periodEnd])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(qty_in), 0) as total_in, COALESCE(SUM(qty_out), 0) as total_out')
            ->first();

        $totalIn = (string) $mutations->total_in;
        $totalOut = (string) $mutations->total_out;
        $closing = bcsub(bcadd($opening, $totalIn, 4), $totalOut, 4);

        return [
            'opening_qty' => $opening,
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'closing_qty' => $closing,
        ];
    }

    /**
     * Laporan Kartu Persediaan — Rincian: histori transaksi detail untuk
     * satu barang dalam satu periode, urut kronologis.
     */
    public function kartuRincian(int $productId, ?int $branchId, string $periodStart, string $periodEnd): Collection
    {
        return DB::table('stock_ledger')
            ->where('product_id', $productId)
            ->whereBetween('transaction_date', [$periodStart, $periodEnd])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
    }

    private function consolidatedOpeningQty(int $productId, string $periodStart): string
    {
        $latestIds = DB::table('stock_ledger')
            ->selectRaw('MAX(id) as id')
            ->where('product_id', $productId)
            ->where('transaction_date', '<', $periodStart)
            ->groupBy('branch_id');

        $rows = DB::table('stock_ledger')
            ->joinSub($latestIds, 'latest', 'stock_ledger.id', '=', 'latest.id')
            ->select('running_qty')
            ->get();

        return $rows->reduce(fn (string $carry, $row) => bcadd($carry, (string) $row->running_qty, 4), '0.0000');
    }
}
