<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\StockAdjustmentException;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockReason;
use App\Models\User;
use App\Services\Accounting\JournalEngine;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi Persediaan / Stock Opname (PRD §13.6, Task 3.6). Selisih plus
 * posts immediately (like a normal receive()); selisih minus ALWAYS waits
 * for approval from someone other than the submitter — unconditional,
 * unlike Pembelian/Aktiva Tetap's ambang-nilai threshold.
 */
class StockAdjustmentService
{
    private const SURPLUS_ADJUSTMENT_ACCOUNT_CODE = '4904'; // Pendapatan Penyesuaian Persediaan

    private const SHRINKAGE_EXPENSE_ACCOUNT_CODE = '5212'; // Beban Susut/Kerugian Persediaan

    public function __construct(
        private readonly StockLedgerEngine $stockLedgerEngine,
        private readonly JournalEngine $journalEngine,
    ) {}

    public function submit(
        Product $product,
        int $branchId,
        string $physicalQty,
        StockReason $reason,
        int $createdBy,
        ?\DateTimeInterface $date = null,
    ): StockAdjustment {
        $systemQty = $this->stockLedgerEngine->currentBalance($product, $branchId)['qty'];
        $variance = bcsub($physicalQty, $systemQty, 4);

        if (bccomp($variance, '0', 4) === 0) {
            throw StockAdjustmentException::noVariance();
        }

        $needsApproval = bccomp($variance, '0', 4) < 0;

        return DB::transaction(function () use ($product, $branchId, $reason, $systemQty, $physicalQty, $variance, $createdBy, $date, $needsApproval) {
            $adjustment = StockAdjustment::query()->create([
                'product_id' => $product->id,
                'branch_id' => $branchId,
                'stock_reason_id' => $reason->id,
                'system_qty' => $systemQty,
                'physical_qty' => $physicalQty,
                'variance_qty' => $variance,
                'adjustment_date' => ($date ?? now())->format('Y-m-d'),
                'status' => 'menunggu_approval',
                'created_by' => $createdBy,
            ]);

            if (! $needsApproval) {
                $this->post($adjustment, $createdBy);
            }

            return $adjustment->fresh();
        });
    }

    public function approve(StockAdjustment $adjustment, User $approver): StockAdjustment
    {
        $this->guardCanDecide($adjustment, $approver);

        return DB::transaction(function () use ($adjustment, $approver) {
            $this->post($adjustment, $adjustment->created_by);
            $adjustment->update(['approved_by' => $approver->id]);

            return $adjustment->fresh();
        });
    }

    public function reject(StockAdjustment $adjustment, User $approver): StockAdjustment
    {
        $this->guardCanDecide($adjustment, $approver);

        $adjustment->update([
            'status' => 'ditolak',
            'approved_by' => $approver->id,
        ]);

        return $adjustment->fresh();
    }

    private function guardCanDecide(StockAdjustment $adjustment, User $approver): void
    {
        if ($adjustment->status !== 'menunggu_approval') {
            throw StockAdjustmentException::notPending($adjustment->status);
        }

        if ($adjustment->created_by === $approver->id) {
            throw StockAdjustmentException::selfApproval();
        }
    }

    private function post(StockAdjustment $adjustment, int $userId): void
    {
        $product = $adjustment->product;
        $absQty = bccomp((string) $adjustment->variance_qty, '0', 4) < 0
            ? bcmul((string) $adjustment->variance_qty, '-1', 4)
            : (string) $adjustment->variance_qty;

        if ($adjustment->isSurplus()) {
            $currentAvgCost = $this->stockLedgerEngine->currentBalance($product, $adjustment->branch_id)['avg_cost'];

            $ledgerEntry = $this->stockLedgerEngine->receive(
                $product,
                $adjustment->branch_id,
                $absQty,
                $currentAvgCost,
                'koreksi_plus',
                $userId,
                $adjustment,
                $adjustment->adjustment_date,
            );

            $amount = bcmul($absQty, $ledgerEntry->unit_cost, 2);

            $lines = [
                ['chart_of_account_id' => $product->coa_inventory_account_id, 'debit' => $amount, 'credit' => 0],
                ['chart_of_account_id' => $this->surplusAccount()->id, 'debit' => 0, 'credit' => $amount],
            ];
        } else {
            $ledgerEntry = $this->stockLedgerEngine->issue(
                $product,
                $adjustment->branch_id,
                $absQty,
                'koreksi_minus',
                $userId,
                $adjustment,
                $adjustment->adjustment_date,
            );

            $amount = bcmul($absQty, $ledgerEntry->unit_cost, 2);

            $lines = [
                ['chart_of_account_id' => $this->shrinkageAccount()->id, 'debit' => $amount, 'credit' => 0],
                ['chart_of_account_id' => $product->coa_inventory_account_id, 'debit' => 0, 'credit' => $amount],
            ];
        }

        $entry = $this->journalEngine->post([
            'branch_id' => $adjustment->branch_id,
            'entry_date' => $adjustment->adjustment_date,
            'description' => "Koreksi persediaan {$product->name} — {$adjustment->stockReason->name}",
            'created_by' => $userId,
            'source' => $adjustment,
            'lines' => $lines,
        ]);

        $adjustment->update([
            'status' => 'diposting',
            'unit_cost' => $ledgerEntry->unit_cost,
            'amount' => $amount,
            'journal_entry_id' => $entry->id,
        ]);
    }

    private function surplusAccount(): ChartOfAccount
    {
        return ChartOfAccount::query()->where('code', self::SURPLUS_ADJUSTMENT_ACCOUNT_CODE)->firstOrFail();
    }

    private function shrinkageAccount(): ChartOfAccount
    {
        return ChartOfAccount::query()->where('code', self::SHRINKAGE_EXPENSE_ACCOUNT_CODE)->firstOrFail();
    }
}
