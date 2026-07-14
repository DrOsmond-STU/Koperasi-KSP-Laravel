<?php

namespace Tests\Feature\Inventory;

use App\Exceptions\Inventory\ReturnException;
use App\Models\ApprovalThreshold;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\SavingsAccount;
use App\Models\StockReason;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\PurchaseService;
use App\Services\Inventory\ReturnService;
use App\Services\Inventory\StockLedgerEngine;
use App\Services\Pos\PosSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md STK-03 (retur wajib mereferensikan transaksi asal)
 * and STK-04 (retur penjualan dinilai sesuai transaksi asal, bukan average
 * cost terkini).
 */
class ReturnServiceTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::factory()->create([
            'coa_inventory_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_cogs_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_sales_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'selling_price' => 2000,
        ]);
    }

    public function test_purchase_return_is_valued_at_current_average_cost_not_original_price(): void
    {
        ApprovalThreshold::query()->create(['module' => 'pembelian', 'threshold_amount' => 5000000]);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $branch = Branch::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->product();
        $user = User::factory()->create();
        $reason = StockReason::factory()->create();

        $purchase = app(PurchaseService::class)->submit(
            $supplier,
            $branch->id,
            'tunai',
            [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '1000']],
            $user->id,
        );

        // Second receipt at a different price shifts the running average away from 1000.
        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '2000', 'pembelian', $user->id);

        $item = $purchase->items->first();
        $return = app(ReturnService::class)->returnPurchase($item, '5', $reason, $user->id);

        $this->assertEquals('1500.0000', $return->unit_cost);
        $this->assertEquals('7500.00', $return->amount);

        $balance = app(StockLedgerEngine::class)->currentBalance($product, $branch->id);
        $this->assertEquals('15.0000', $balance['qty']);
    }

    public function test_purchase_return_exceeding_original_qty_is_rejected(): void
    {
        ApprovalThreshold::query()->create(['module' => 'pembelian', 'threshold_amount' => 5000000]);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $branch = Branch::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->product();
        $user = User::factory()->create();
        $reason = StockReason::factory()->create();

        $purchase = app(PurchaseService::class)->submit(
            $supplier,
            $branch->id,
            'tunai',
            [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '1000']],
            $user->id,
        );

        $this->expectException(ReturnException::class);
        app(ReturnService::class)->returnPurchase($purchase->items->first(), '11', $reason, $user->id);
    }

    public function test_purchase_return_requires_a_posted_purchase(): void
    {
        ApprovalThreshold::query()->create(['module' => 'pembelian', 'threshold_amount' => 100]);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $branch = Branch::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->product();
        $user = User::factory()->create();
        $reason = StockReason::factory()->create();

        $purchase = app(PurchaseService::class)->submit(
            $supplier,
            $branch->id,
            'tunai',
            [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '1000']],
            $user->id,
        );

        $this->assertEquals('menunggu_approval', $purchase->status);

        $this->expectException(ReturnException::class);
        app(ReturnService::class)->returnPurchase($purchase->items->first(), '1', $reason, $user->id);
    }

    public function test_sales_return_uses_original_unit_cost_and_price_not_current_average(): void
    {
        ChartOfAccount::factory()->create(['code' => '1101']);

        $branch = Branch::factory()->create();
        $product = $this->product();
        $user = User::factory()->create();

        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id);

        $sale = app(PosSaleService::class)->sell(
            $branch->id,
            'tunai',
            [['product_id' => $product->id, 'qty' => '4']],
            $user->id,
        );

        // Average cost shifts after the sale — the return must still use the sale's own recorded cost.
        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '5000', 'pembelian', $user->id);

        $item = $sale->items->first();
        $return = app(ReturnService::class)->returnSale($item, '2', $user->id);

        $this->assertEquals('1000.0000', $return->unit_cost);
        $this->assertEquals('2000.00', $return->unit_price);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $return->journal_entry_id,
            'chart_of_account_id' => $product->coa_sales_revenue_account_id,
            'debit' => '4000.00',
        ]);
    }

    public function test_sales_return_exceeding_original_qty_is_rejected(): void
    {
        ChartOfAccount::factory()->create(['code' => '1101']);

        $branch = Branch::factory()->create();
        $product = $this->product();
        $user = User::factory()->create();

        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id);

        $sale = app(PosSaleService::class)->sell(
            $branch->id,
            'tunai',
            [['product_id' => $product->id, 'qty' => '4']],
            $user->id,
        );

        $this->expectException(ReturnException::class);
        app(ReturnService::class)->returnSale($sale->items->first(), '5', $user->id);
    }

    public function test_sales_return_credits_savings_balance_for_potong_simpanan_sale(): void
    {
        $branch = Branch::factory()->create();
        $product = $this->product();
        $user = User::factory()->create();
        $account = SavingsAccount::factory()->create(['branch_id' => $branch->id, 'balance' => 10000]);

        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id);

        $sale = app(PosSaleService::class)->sell(
            $branch->id,
            'potong_simpanan',
            [['product_id' => $product->id, 'qty' => '4']],
            $user->id,
            $account,
        );

        $this->assertEquals('2000.00', $account->fresh()->balance);

        app(ReturnService::class)->returnSale($sale->items->first(), '2', $user->id);

        $this->assertEquals('6000.00', $account->fresh()->balance);
        $this->assertDatabaseHas('savings_transactions', [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'amount' => '4000.00',
        ]);
    }
}
