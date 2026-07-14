<?php

namespace Tests\Feature\Inventory;

use App\Exceptions\Inventory\PurchaseApprovalException;
use App\Exceptions\Inventory\PurchasePaymentException;
use App\Models\ApprovalThreshold;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\PurchaseService;
use App\Services\Inventory\StockLedgerEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md STK-01 (jurnal + stok terbentuk bersamaan),
 * AUTH-09/AUTH-12 (approval berjenjang di atas ambang, create != approve).
 */
class PurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        return Product::factory()->create([
            'coa_inventory_account_id' => ChartOfAccount::factory()->create(['code' => '1301'])->id,
        ]);
    }

    public function test_purchase_below_threshold_posts_immediately_and_updates_stock(): void
    {
        ApprovalThreshold::query()->create(['module' => 'pembelian', 'threshold_amount' => 5000000]);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $branch = Branch::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->makeProduct();
        $user = User::factory()->create();

        $purchase = app(PurchaseService::class)->submit(
            $supplier,
            $branch->id,
            'tunai',
            [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '1000']],
            $user->id,
        );

        $this->assertEquals('diposting', $purchase->status);
        $this->assertNotNull($purchase->journal_entry_id);
        $this->assertEquals('10000.00', $purchase->total_amount);

        $balance = app(StockLedgerEngine::class)->currentBalance($product, $branch->id);
        $this->assertEquals('10.0000', $balance['qty']);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $purchase->journal_entry_id,
            'debit' => '10000.00',
        ]);
    }

    public function test_purchase_above_threshold_waits_for_approval_before_posting(): void
    {
        ApprovalThreshold::query()->create(['module' => 'pembelian', 'threshold_amount' => 5000]);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $branch = Branch::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->makeProduct();
        $user = User::factory()->create();

        $purchase = app(PurchaseService::class)->submit(
            $supplier,
            $branch->id,
            'tunai',
            [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '1000']],
            $user->id,
        );

        $this->assertEquals('menunggu_approval', $purchase->status);
        $this->assertNull($purchase->journal_entry_id);

        $balance = app(StockLedgerEngine::class)->currentBalance($product, $branch->id);
        $this->assertEquals('0.0000', $balance['qty']);
    }

    public function test_creator_cannot_approve_own_purchase(): void
    {
        ApprovalThreshold::query()->create(['module' => 'pembelian', 'threshold_amount' => 5000]);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $branch = Branch::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->makeProduct();
        $user = User::factory()->create();

        $purchase = app(PurchaseService::class)->submit(
            $supplier,
            $branch->id,
            'tunai',
            [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '1000']],
            $user->id,
        );

        $this->expectException(PurchaseApprovalException::class);
        app(PurchaseService::class)->approve($purchase, $user);
    }

    public function test_different_approver_posts_the_purchase(): void
    {
        ApprovalThreshold::query()->create(['module' => 'pembelian', 'threshold_amount' => 5000]);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $branch = Branch::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->makeProduct();
        $creator = User::factory()->create();
        $approver = User::factory()->create();

        $purchase = app(PurchaseService::class)->submit(
            $supplier,
            $branch->id,
            'kredit',
            [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '1000']],
            $creator->id,
        );

        $posted = app(PurchaseService::class)->approve($purchase, $approver);

        $this->assertEquals('diposting', $posted->status);
        $this->assertEquals($approver->id, $posted->approved_by);
        $this->assertEquals('belum_lunas', $posted->payment_status);

        $balance = app(StockLedgerEngine::class)->currentBalance($product, $branch->id);
        $this->assertEquals('10.0000', $balance['qty']);
    }

    public function test_payment_settles_debt_and_rejects_overpayment(): void
    {
        ApprovalThreshold::query()->create(['module' => 'pembelian', 'threshold_amount' => 5000000]);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $branch = Branch::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->makeProduct();
        $user = User::factory()->create();

        $purchase = app(PurchaseService::class)->submit(
            $supplier,
            $branch->id,
            'kredit',
            [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '1000']],
            $user->id,
        );

        $this->expectException(PurchasePaymentException::class);
        app(PurchaseService::class)->pay($purchase, '20000', $user->id);
    }

    public function test_full_payment_marks_purchase_as_lunas(): void
    {
        ApprovalThreshold::query()->create(['module' => 'pembelian', 'threshold_amount' => 5000000]);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $branch = Branch::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->makeProduct();
        $user = User::factory()->create();

        $purchase = app(PurchaseService::class)->submit(
            $supplier,
            $branch->id,
            'kredit',
            [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '1000']],
            $user->id,
        );

        app(PurchaseService::class)->pay($purchase, '10000', $user->id);

        $this->assertEquals('lunas', $purchase->fresh()->payment_status);
        $this->assertEquals('0.00', $purchase->fresh()->remainingAmount());
    }
}
