<?php

namespace Tests\Feature\Inventory;

use App\Exceptions\Inventory\StockAdjustmentException;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\StockReason;
use App\Models\User;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Inventory\StockLedgerEngine;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md STK-05 (selisih minus menunggu approval, tidak
 * langsung menjurnal) and STK-06 (approval oleh pembuat entri yang sama
 * ditolak).
 */
class StockAdjustmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_surplus_variance_posts_immediately(): void
    {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $user = User::factory()->create();
        $reason = StockReason::factory()->create();

        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id);

        $adjustment = app(StockAdjustmentService::class)->submit($product, $branch->id, '15', $reason, $user->id);

        $this->assertEquals('diposting', $adjustment->status);
        $this->assertEquals('5.0000', $adjustment->variance_qty);
        $this->assertNotNull($adjustment->journal_entry_id);

        $balance = app(StockLedgerEngine::class)->currentBalance($product, $branch->id);
        $this->assertEquals('15.0000', $balance['qty']);

        $surplusAccountId = ChartOfAccount::query()->where('code', '4904')->value('id');
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $adjustment->journal_entry_id,
            'chart_of_account_id' => $surplusAccountId,
            'credit' => '5000.00',
        ]);
    }

    public function test_deficit_variance_waits_for_approval_and_does_not_post(): void
    {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $user = User::factory()->create();
        $reason = StockReason::factory()->create();

        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id);

        $adjustment = app(StockAdjustmentService::class)->submit($product, $branch->id, '6', $reason, $user->id);

        $this->assertEquals('menunggu_approval', $adjustment->status);
        $this->assertEquals('-4.0000', $adjustment->variance_qty);
        $this->assertNull($adjustment->journal_entry_id);

        $balance = app(StockLedgerEngine::class)->currentBalance($product, $branch->id);
        $this->assertEquals('10.0000', $balance['qty']);
    }

    public function test_creator_cannot_approve_own_deficit_adjustment(): void
    {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $user = User::factory()->create();
        $reason = StockReason::factory()->create();

        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id);
        $adjustment = app(StockAdjustmentService::class)->submit($product, $branch->id, '6', $reason, $user->id);

        $this->expectException(StockAdjustmentException::class);
        app(StockAdjustmentService::class)->approve($adjustment, $user);
    }

    public function test_different_approver_posts_the_deficit_adjustment(): void
    {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $creator = User::factory()->create();
        $approver = User::factory()->create();
        $reason = StockReason::factory()->create();

        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $creator->id);
        $adjustment = app(StockAdjustmentService::class)->submit($product, $branch->id, '6', $reason, $creator->id);

        $posted = app(StockAdjustmentService::class)->approve($adjustment, $approver);

        $this->assertEquals('diposting', $posted->status);
        $this->assertEquals($approver->id, $posted->approved_by);

        $balance = app(StockLedgerEngine::class)->currentBalance($product, $branch->id);
        $this->assertEquals('6.0000', $balance['qty']);

        $shrinkageAccountId = ChartOfAccount::query()->where('code', '5212')->value('id');
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $posted->journal_entry_id,
            'chart_of_account_id' => $shrinkageAccountId,
            'debit' => '4000.00',
        ]);
    }

    public function test_zero_variance_is_rejected(): void
    {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $user = User::factory()->create();
        $reason = StockReason::factory()->create();

        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id);

        $this->expectException(StockAdjustmentException::class);
        app(StockAdjustmentService::class)->submit($product, $branch->id, '10', $reason, $user->id);
    }
}
