<?php

namespace App\Services\Pos;

use App\Models\ChartOfAccount;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\SavingsAccount;
use App\Services\Accounting\JournalEngine;
use App\Services\Inventory\StockLedgerEngine;
use App\Services\Loans\LoanService;
use App\Services\Savings\SavingsService;
use Illuminate\Support\Facades\DB;

/**
 * Transaksi Penjualan POS (PRD §13.3, Task 3.4). No approval workflow —
 * posts stock (via StockLedgerEngine) and a single combined journal (via
 * JournalEngine) immediately, same as Kas Teller. HPP is captured from the
 * average cost AT THE MOMENT of sale (STK-02), never recomputed later.
 */
class PosSaleService
{
    private const DEFAULT_CASH_ACCOUNT_CODE = '1101';

    public function __construct(
        private readonly StockLedgerEngine $stockLedgerEngine,
        private readonly JournalEngine $journalEngine,
        private readonly SavingsService $savingsService,
        private readonly LoanService $loanService,
    ) {}

    /**
     * @param  array<int, array{product_id: int, qty: string}>  $items
     * @param  array{member: \App\Models\Member, loan_product: \App\Models\LoanProduct, tenor_months: int}|null  $hutang
     */
    public function sell(
        int $branchId,
        string $paymentMethod,
        array $items,
        int $createdBy,
        ?SavingsAccount $savingsAccount = null,
        ?\DateTimeInterface $date = null,
        ?array $hutang = null,
    ): PosSale {
        return DB::transaction(function () use ($branchId, $paymentMethod, $items, $createdBy, $savingsAccount, $date, $hutang) {
            $sale = PosSale::query()->create([
                'branch_id' => $branchId,
                'savings_account_id' => $savingsAccount?->id,
                'sale_number' => $this->generateSaleNumber(),
                'sale_date' => ($date ?? now())->format('Y-m-d'),
                'payment_method' => $paymentMethod,
                'total_amount' => 0,
                'total_cogs' => 0,
                'created_by' => $createdBy,
            ]);

            $totalAmount = '0.00';
            $totalCogs = '0.00';
            $journalLines = [];
            $itemRows = [];

            foreach ($items as $item) {
                $product = Product::query()->findOrFail($item['product_id']);
                $qty = (string) $item['qty'];
                $unitPrice = (string) $product->selling_price;
                $subtotal = bcmul($qty, $unitPrice, 2);

                $ledgerEntry = $this->stockLedgerEngine->issue(
                    $product,
                    $branchId,
                    $qty,
                    'penjualan',
                    $createdBy,
                    $sale,
                    $date,
                );

                $cogsAmount = bcmul($qty, $ledgerEntry->unit_cost, 2);

                $totalAmount = bcadd($totalAmount, $subtotal, 2);
                $totalCogs = bcadd($totalCogs, $cogsAmount, 2);

                $itemRows[] = [
                    'pos_sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $ledgerEntry->unit_cost,
                    'subtotal' => $subtotal,
                ];

                $journalLines[] = ['chart_of_account_id' => $product->coa_sales_revenue_account_id, 'debit' => 0, 'credit' => $subtotal];
                $journalLines[] = ['chart_of_account_id' => $product->coa_cogs_account_id, 'debit' => $cogsAmount, 'credit' => 0];
                $journalLines[] = ['chart_of_account_id' => $product->coa_inventory_account_id, 'debit' => 0, 'credit' => $cogsAmount];
            }

            $loan = null;
            if ($paymentMethod === 'hutang') {
                // Fails fast, before any journal line is built — throwing here
                // rolls back the whole DB::transaction, including the
                // stock-ledger issue() rows already written by the items loop.
                $loan = $this->loanService->originateInstantly(
                    $hutang['member'],
                    $hutang['loan_product'],
                    (float) $totalAmount,
                    $hutang['tenor_months'],
                    $branchId,
                    $createdBy,
                );
            }

            $debitAccountId = match ($paymentMethod) {
                'potong_simpanan' => $savingsAccount->savingsProduct->coa_liability_account_id,
                'hutang' => $hutang['loan_product']->coa_receivable_account_id,
                default => $this->cashAccount()->id,
            };

            $journalLines[] = ['chart_of_account_id' => $debitAccountId, 'debit' => $totalAmount, 'credit' => 0];

            $entry = $this->journalEngine->post([
                'branch_id' => $branchId,
                'entry_date' => ($date ?? now())->format('Y-m-d'),
                'description' => "Penjualan POS {$sale->sale_number}",
                'created_by' => $createdBy,
                'source' => $sale,
                'lines' => $journalLines,
            ]);

            if ($paymentMethod === 'potong_simpanan') {
                $this->savingsService->debitBalanceForNonCashTransaction(
                    $savingsAccount,
                    $totalAmount,
                    $entry->id,
                    $createdBy,
                    "Belanja POS {$sale->sale_number}",
                );
            }

            foreach ($itemRows as $row) {
                $sale->items()->create($row);
            }

            $sale->update([
                'total_amount' => $totalAmount,
                'total_cogs' => $totalCogs,
                'journal_entry_id' => $entry->id,
                'loan_id' => $loan?->id,
            ]);

            return $sale->fresh('items');
        });
    }

    private function generateSaleNumber(): string
    {
        do {
            $candidate = 'JL-'.now()->format('ymd').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (PosSale::query()->where('sale_number', $candidate)->exists());

        return $candidate;
    }

    private function cashAccount(): ChartOfAccount
    {
        return ChartOfAccount::query()->where('code', self::DEFAULT_CASH_ACCOUNT_CODE)->firstOrFail();
    }
}
