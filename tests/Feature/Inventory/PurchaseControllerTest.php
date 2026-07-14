<?php

namespace Tests\Feature\Inventory;

use App\Models\ApprovalThreshold;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\PurchaseTransaction;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md E2E-07 (Staf input pembelian -> Manajer approve di
 * atas ambang -> stok bertambah, hutang tercatat bila kredit) end-to-end
 * over HTTP, plus AUTH permission gating.
 */
class PurchaseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    public function test_role_without_permission_cannot_create_purchase(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $branch = Branch::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.pembelian.store'), [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'payment_method' => 'tunai',
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_price' => 1000]],
        ]);

        $response->assertForbidden();
    }

    public function test_full_flow_creator_submits_and_different_manajer_approves(): void
    {
        ApprovalThreshold::query()->create(['module' => 'pembelian', 'threshold_amount' => 5000]);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $creator = $this->manajer();
        $approver = $this->manajer();

        $branch = Branch::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'coa_inventory_account_id' => ChartOfAccount::factory()->create()->id,
        ]);

        $storeResponse = $this->actingAs($creator)->post(route('admin.pembelian.store'), [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'payment_method' => 'kredit',
            'items' => [
                ['product_id' => $product->id, 'qty' => 10, 'unit_price' => 1000],
                ['product_id' => '', 'qty' => '', 'unit_price' => ''],
            ],
        ]);
        $storeResponse->assertRedirect(route('admin.pembelian.index'));

        $purchase = PurchaseTransaction::query()->firstOrFail();
        $this->assertEquals('menunggu_approval', $purchase->status);

        $selfApproveResponse = $this->actingAs($creator)->post(route('admin.pembelian.decide', $purchase), [
            'decision' => 'setuju',
        ]);
        $selfApproveResponse->assertRedirect(route('admin.pembelian.index'));
        $this->assertEquals('menunggu_approval', $purchase->fresh()->status);

        $approveResponse = $this->actingAs($approver)->post(route('admin.pembelian.decide', $purchase), [
            'decision' => 'setuju',
        ]);
        $approveResponse->assertRedirect(route('admin.pembelian.index'));

        $posted = $purchase->fresh();
        $this->assertEquals('diposting', $posted->status);
        $this->assertEquals('belum_lunas', $posted->payment_status);
    }
}
