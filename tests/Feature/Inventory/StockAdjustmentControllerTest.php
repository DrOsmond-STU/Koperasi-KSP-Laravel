<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockReason;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Inventory\StockLedgerEngine;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_role_without_permission_cannot_submit_adjustment(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $reason = StockReason::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.persediaan.koreksi.store'), [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'physical_qty' => 5,
            'stock_reason_id' => $reason->id,
        ]);

        $response->assertForbidden();
    }

    public function test_manajer_can_submit_and_a_different_manajer_approves_a_deficit(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $creator = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $creator->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $creator->id, 'scope_type' => 'all']);

        $approver = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $approver->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $approver->id, 'scope_type' => 'all']);

        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $reason = StockReason::factory()->create();

        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $creator->id);

        $storeResponse = $this->actingAs($creator)->post(route('admin.persediaan.koreksi.store'), [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'physical_qty' => 6,
            'stock_reason_id' => $reason->id,
        ]);
        $storeResponse->assertRedirect(route('admin.persediaan.koreksi.index'));

        $adjustment = StockAdjustment::query()->firstOrFail();
        $this->assertEquals('menunggu_approval', $adjustment->status);

        $selfApprove = $this->actingAs($creator)->post(route('admin.persediaan.koreksi.decide', $adjustment), ['decision' => 'setuju']);
        $selfApprove->assertRedirect(route('admin.persediaan.koreksi.index'));
        $this->assertEquals('menunggu_approval', $adjustment->fresh()->status);

        $approve = $this->actingAs($approver)->post(route('admin.persediaan.koreksi.decide', $adjustment), ['decision' => 'setuju']);
        $approve->assertRedirect(route('admin.persediaan.koreksi.index'));
        $this->assertEquals('diposting', $adjustment->fresh()->status);
    }

    public function test_creator_can_cancel_own_posted_surplus_adjustment(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $creator = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $creator->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $creator->id, 'scope_type' => 'all']);

        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $reason = StockReason::factory()->create();
        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $creator->id);

        $this->actingAs($creator)->post(route('admin.persediaan.koreksi.store'), [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'physical_qty' => 15,
            'stock_reason_id' => $reason->id,
        ]);
        $adjustment = StockAdjustment::query()->firstOrFail();
        $this->assertEquals('diposting', $adjustment->status);

        $response = $this->actingAs($creator)->post(route('admin.persediaan.koreksi.cancel', $adjustment), [
            'reason' => 'Salah input stok fisik',
        ]);

        $response->assertRedirect(route('admin.persediaan.koreksi.index'));
        $this->assertEquals('dibatalkan', $adjustment->fresh()->status);
    }

    public function test_other_manajer_at_different_scope_cannot_cancel_someone_elses_adjustment(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $creator = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $creator->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $creator->id, 'scope_type' => 'all']);

        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $reason = StockReason::factory()->create();
        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $creator->id);

        $this->actingAs($creator)->post(route('admin.persediaan.koreksi.store'), [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'physical_qty' => 15,
            'stock_reason_id' => $reason->id,
        ]);
        $adjustment = StockAdjustment::query()->firstOrFail();

        $teller = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $teller->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $teller->id, 'scope_type' => 'all']);

        $this->actingAs($teller)->post(route('admin.persediaan.koreksi.cancel', $adjustment), [
            'reason' => 'Coba batalkan tanpa izin',
        ])->assertForbidden();

        $this->assertEquals('diposting', $adjustment->fresh()->status);
    }
}
