<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\Product;
use App\Models\StockLedgerEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The single writer for `stock_ledger` (PRD §13.7, ARCHITECTURE — mirrors
 * JournalEngine's role for the accounting ledger). No module may mutate
 * stock directly; Pembelian, Penjualan POS, Retur, and Koreksi Persediaan
 * all call receive()/issue() here.
 *
 * Running balance and average cost are recomputed from the *previous*
 * stock_ledger row for the same (product, branch) — never denormalized
 * onto `products` — so a `SELECT ... FOR UPDATE` on the product row is
 * used to serialize concurrent writers for the same product (STK-09).
 * Money/qty math uses bcmath strings throughout to avoid float drift.
 */
class StockLedgerEngine
{
    private const QTY_SCALE = 4;

    /**
     * Adds stock (Pembelian, Retur Penjualan, Koreksi Plus). Blends
     * $unitCost into the running weighted-average cost.
     */
    public function receive(
        Product $product,
        int $branchId,
        string $qty,
        string $unitCost,
        string $transactionType,
        int $createdBy,
        ?Model $source = null,
        ?\DateTimeInterface $date = null,
    ): StockLedgerEntry {
        return DB::transaction(function () use ($product, $branchId, $qty, $unitCost, $transactionType, $createdBy, $source, $date) {
            Product::query()->whereKey($product->id)->lockForUpdate()->first();

            $previous = $this->lastEntry($product->id, $branchId);
            $previousQty = $previous?->running_qty ?? '0.0000';
            $previousAvgCost = $previous?->running_avg_cost ?? '0.0000';

            $newQty = bcadd($previousQty, $qty, self::QTY_SCALE);
            $previousTotalCost = bcmul($previousQty, $previousAvgCost, self::QTY_SCALE + 4);
            $incomingTotalCost = bcmul($qty, $unitCost, self::QTY_SCALE + 4);
            $newTotalCost = bcadd($previousTotalCost, $incomingTotalCost, self::QTY_SCALE + 4);
            $newAvgCost = bccomp($newQty, '0', self::QTY_SCALE) > 0
                ? bcdiv($newTotalCost, $newQty, self::QTY_SCALE)
                : '0.0000';

            return StockLedgerEntry::query()->create([
                'product_id' => $product->id,
                'branch_id' => $branchId,
                'transaction_date' => ($date ?? now())->format('Y-m-d'),
                'transaction_type' => $transactionType,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'qty_in' => $qty,
                'qty_out' => 0,
                'unit_cost' => $unitCost,
                'running_qty' => $newQty,
                'running_avg_cost' => $newAvgCost,
                'created_by' => $createdBy,
            ]);
        });
    }

    /**
     * Removes stock (Penjualan POS, Retur Pembelian, Koreksi Minus) valued
     * at the CURRENT running average cost (STK-02). Rejects (no row
     * written) if $qty exceeds the running balance (STK-07).
     */
    public function issue(
        Product $product,
        int $branchId,
        string $qty,
        string $transactionType,
        int $createdBy,
        ?Model $source = null,
        ?\DateTimeInterface $date = null,
    ): StockLedgerEntry {
        return DB::transaction(function () use ($product, $branchId, $qty, $transactionType, $createdBy, $source, $date) {
            Product::query()->whereKey($product->id)->lockForUpdate()->first();

            $previous = $this->lastEntry($product->id, $branchId);
            $previousQty = $previous?->running_qty ?? '0.0000';
            $avgCost = $previous?->running_avg_cost ?? '0.0000';

            if (bccomp($qty, $previousQty, self::QTY_SCALE) > 0) {
                throw new InsufficientStockException($product->code, $previousQty, $qty);
            }

            $newQty = bcsub($previousQty, $qty, self::QTY_SCALE);

            return StockLedgerEntry::query()->create([
                'product_id' => $product->id,
                'branch_id' => $branchId,
                'transaction_date' => ($date ?? now())->format('Y-m-d'),
                'transaction_type' => $transactionType,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'qty_in' => 0,
                'qty_out' => $qty,
                'unit_cost' => $avgCost,
                'running_qty' => $newQty,
                'running_avg_cost' => $avgCost,
                'created_by' => $createdBy,
            ]);
        });
    }

    /**
     * @return array{qty: string, avg_cost: string}
     */
    public function currentBalance(Product $product, int $branchId): array
    {
        $entry = $this->lastEntry($product->id, $branchId);

        return [
            'qty' => $entry?->running_qty ?? '0.0000',
            'avg_cost' => $entry?->running_avg_cost ?? '0.0000',
        ];
    }

    private function lastEntry(int $productId, int $branchId): ?StockLedgerEntry
    {
        return StockLedgerEntry::query()
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->latest('id')
            ->first();
    }
}
