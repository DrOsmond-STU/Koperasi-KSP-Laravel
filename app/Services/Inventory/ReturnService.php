<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\ReturnException;
use App\Models\ChartOfAccount;
use App\Models\PosSaleItem;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\SalesReturn;
use App\Models\StockReason;
use App\Services\Accounting\JournalEngine;
use App\Services\Savings\SavingsService;
use Illuminate\Support\Facades\DB;

/**
 * Retur Pembelian & Retur Penjualan (PRD §13.5, Task 3.5). Both always
 * reference the original transaction line (STK-03) and never mutate the
 * original purchase/sale row — each return is its own independent journal
 * entry, same philosophy as JournalEngine::reverse().
 */
class ReturnService
{
    private const DEFAULT_CASH_ACCOUNT_CODE = '1101';

    public function __construct(
        private readonly StockLedgerEngine $stockLedgerEngine,
        private readonly JournalEngine $journalEngine,
        private readonly SavingsService $savingsService,
    ) {}

    /**
     * Retur Pembelian: valued at the CURRENT average cost (StockLedgerEngine
     * issue()), not the original purchase price.
     */
    public function returnPurchase(
        PurchaseItem $item,
        string $qty,
        StockReason $reason,
        int $createdBy,
        ?\DateTimeInterface $date = null,
    ): PurchaseReturn {
        $purchase = $item->purchaseTransaction;

        if ($purchase->status !== 'diposting') {
            throw ReturnException::purchaseNotPosted();
        }

        $available = bcsub((string) $item->qty, $item->alreadyReturnedQty(), 4);

        if (bccomp($qty, $available, 4) > 0) {
            throw ReturnException::exceedsOriginalQty($available, $qty);
        }

        return DB::transaction(function () use ($item, $purchase, $qty, $reason, $createdBy, $date) {
            $return = PurchaseReturn::query()->create([
                'purchase_item_id' => $item->id,
                'branch_id' => $purchase->branch_id,
                'stock_reason_id' => $reason->id,
                'qty' => $qty,
                'return_date' => ($date ?? now())->format('Y-m-d'),
                'created_by' => $createdBy,
            ]);

            $ledgerEntry = $this->stockLedgerEngine->issue(
                $item->product,
                $purchase->branch_id,
                $qty,
                'retur_pembelian',
                $createdBy,
                $return,
                $date,
            );

            $amount = bcmul($qty, $ledgerEntry->unit_cost, 2);

            $debitAccountId = $purchase->isKredit()
                ? $purchase->supplier->coa_payable_account_id
                : $this->cashAccount()->id;

            $entry = $this->journalEngine->post([
                'branch_id' => $purchase->branch_id,
                'entry_date' => ($date ?? now())->format('Y-m-d'),
                'description' => "Retur pembelian {$purchase->purchase_number} — {$item->product->name}",
                'created_by' => $createdBy,
                'source' => $return,
                'lines' => [
                    ['chart_of_account_id' => $debitAccountId, 'debit' => $amount, 'credit' => 0],
                    ['chart_of_account_id' => $item->product->coa_inventory_account_id, 'debit' => 0, 'credit' => $amount],
                ],
            ]);

            $return->update([
                'unit_cost' => $ledgerEntry->unit_cost,
                'amount' => $amount,
                'journal_entry_id' => $entry->id,
            ]);

            return $return->fresh();
        });
    }

    /**
     * Retur Penjualan: valued at the ORIGINAL sale's unit_price/unit_cost
     * (STK-04) — never the average cost prevailing at return time.
     */
    public function returnSale(
        PosSaleItem $item,
        string $qty,
        int $createdBy,
        ?\DateTimeInterface $date = null,
    ): SalesReturn {
        $sale = $item->posSale;

        $available = bcsub((string) $item->qty, $item->alreadyReturnedQty(), 4);

        if (bccomp($qty, $available, 4) > 0) {
            throw ReturnException::exceedsOriginalQty($available, $qty);
        }

        $revenueAmount = bcmul($qty, (string) $item->unit_price, 2);
        $cogsAmount = bcmul($qty, (string) $item->unit_cost, 2);

        return DB::transaction(function () use ($item, $sale, $qty, $revenueAmount, $cogsAmount, $createdBy, $date) {
            $return = SalesReturn::query()->create([
                'pos_sale_item_id' => $item->id,
                'branch_id' => $sale->branch_id,
                'qty' => $qty,
                'unit_price' => $item->unit_price,
                'unit_cost' => $item->unit_cost,
                'return_date' => ($date ?? now())->format('Y-m-d'),
                'created_by' => $createdBy,
            ]);

            $this->stockLedgerEngine->receive(
                $item->product,
                $sale->branch_id,
                $qty,
                (string) $item->unit_cost,
                'retur_penjualan',
                $createdBy,
                $return,
                $date,
            );

            $creditAccountId = $sale->isPotongSimpanan()
                ? $sale->savingsAccount->savingsProduct->coa_liability_account_id
                : $this->cashAccount()->id;

            $entry = $this->journalEngine->post([
                'branch_id' => $sale->branch_id,
                'entry_date' => ($date ?? now())->format('Y-m-d'),
                'description' => "Retur penjualan {$sale->sale_number} — {$item->product->name}",
                'created_by' => $createdBy,
                'source' => $return,
                'lines' => [
                    ['chart_of_account_id' => $item->product->coa_sales_revenue_account_id, 'debit' => $revenueAmount, 'credit' => 0],
                    ['chart_of_account_id' => $creditAccountId, 'debit' => 0, 'credit' => $revenueAmount],
                    ['chart_of_account_id' => $item->product->coa_inventory_account_id, 'debit' => $cogsAmount, 'credit' => 0],
                    ['chart_of_account_id' => $item->product->coa_cogs_account_id, 'debit' => 0, 'credit' => $cogsAmount],
                ],
            ]);

            if ($sale->isPotongSimpanan()) {
                $this->savingsService->creditBalanceForNonCashTransaction(
                    $sale->savingsAccount,
                    $revenueAmount,
                    $entry->id,
                    $createdBy,
                    "Retur POS {$sale->sale_number}",
                );
            }

            $return->update(['journal_entry_id' => $entry->id]);

            return $return->fresh();
        });
    }

    private function cashAccount(): ChartOfAccount
    {
        return ChartOfAccount::query()->where('code', self::DEFAULT_CASH_ACCOUNT_CODE)->firstOrFail();
    }
}
