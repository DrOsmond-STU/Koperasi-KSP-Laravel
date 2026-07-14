<?php

namespace Tests\Feature\Inventory;

use App\Exceptions\Inventory\InsufficientStockException;
use App\Exceptions\Savings\InsufficientBalanceException;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Inventory\StockLedgerEngine;
use App\Services\Pos\PosSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md STK-02 (HPP dari average cost berjalan), STK-07
 * (stok tidak cukup ditolak), and E2E-06 (jual -> HPP -> jurnal + stok
 * berkurang sesuai Stock Ledger Engine).
 */
class PosSaleServiceTest extends TestCase
{
    use RefreshDatabase;

    private function stockedProduct(int $branchId, string $qty = '10', string $cost = '1000'): Product
    {
        $product = Product::factory()->create([
            'coa_inventory_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_cogs_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_sales_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'selling_price' => 2000,
        ]);

        app(StockLedgerEngine::class)->receive($product, $branchId, $qty, $cost, 'pembelian', User::factory()->create()->id);

        return $product;
    }

    public function test_cash_sale_reduces_stock_and_posts_revenue_and_cogs_journal(): void
    {
        ChartOfAccount::factory()->create(['code' => '1101']);
        $branch = Branch::factory()->create();
        $product = $this->stockedProduct($branch->id);
        $user = User::factory()->create();

        $sale = app(PosSaleService::class)->sell(
            $branch->id,
            'tunai',
            [['product_id' => $product->id, 'qty' => '3']],
            $user->id,
        );

        $this->assertEquals('6000.00', $sale->total_amount);
        $this->assertEquals('3000.00', $sale->total_cogs);
        $this->assertNotNull($sale->journal_entry_id);

        $balance = app(StockLedgerEngine::class)->currentBalance($product, $branch->id);
        $this->assertEquals('7.0000', $balance['qty']);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $sale->journal_entry_id,
            'chart_of_account_id' => $product->coa_cogs_account_id,
            'debit' => '3000.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $sale->journal_entry_id,
            'chart_of_account_id' => $product->coa_sales_revenue_account_id,
            'credit' => '6000.00',
        ]);
    }

    public function test_selling_more_than_available_stock_is_rejected(): void
    {
        ChartOfAccount::factory()->create(['code' => '1101']);
        $branch = Branch::factory()->create();
        $product = $this->stockedProduct($branch->id, qty: '2');
        $user = User::factory()->create();

        $this->expectException(InsufficientStockException::class);
        app(PosSaleService::class)->sell(
            $branch->id,
            'tunai',
            [['product_id' => $product->id, 'qty' => '5']],
            $user->id,
        );

        $this->assertDatabaseCount('pos_sales', 0);
    }

    public function test_potong_simpanan_debits_member_balance_instead_of_cash(): void
    {
        $branch = Branch::factory()->create();
        $product = $this->stockedProduct($branch->id);
        $user = User::factory()->create();
        $account = SavingsAccount::factory()->create(['branch_id' => $branch->id, 'balance' => 50000]);

        $sale = app(PosSaleService::class)->sell(
            $branch->id,
            'potong_simpanan',
            [['product_id' => $product->id, 'qty' => '3']],
            $user->id,
            $account,
        );

        $this->assertEquals('44000.00', $account->fresh()->balance);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $sale->journal_entry_id,
            'chart_of_account_id' => $account->savingsProduct->coa_liability_account_id,
            'debit' => '6000.00',
        ]);
        $this->assertDatabaseHas('savings_transactions', [
            'savings_account_id' => $account->id,
            'type' => 'tarik',
            'amount' => '6000.00',
        ]);
    }

    public function test_potong_simpanan_with_insufficient_balance_is_rejected_and_stock_is_not_reduced(): void
    {
        $branch = Branch::factory()->create();
        $product = $this->stockedProduct($branch->id);
        $user = User::factory()->create();
        $account = SavingsAccount::factory()->create(['branch_id' => $branch->id, 'balance' => 1000]);

        $this->expectException(InsufficientBalanceException::class);
        app(PosSaleService::class)->sell(
            $branch->id,
            'potong_simpanan',
            [['product_id' => $product->id, 'qty' => '3']],
            $user->id,
            $account,
        );

        $balance = app(StockLedgerEngine::class)->currentBalance($product, $branch->id);
        $this->assertEquals('10.0000', $balance['qty']);
        $this->assertEquals('1000.00', $account->fresh()->balance);
    }
}
