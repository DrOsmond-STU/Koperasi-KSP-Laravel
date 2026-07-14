<?php

namespace Tests\Feature\FixedAsset;

use App\Models\ApprovalThreshold;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md DEP-07 (nilai residu > nilai perolehan ditolak saat
 * pembuatan) and E2E-09 (bagian pembelian): Manajer tambah aktiva tetap ->
 * Manajer lain approve di atas ambang -> aset aktif.
 */
class FixedAssetControllerTest extends TestCase
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

    public function test_residual_value_greater_than_acquisition_cost_is_rejected(): void
    {
        $user = $this->manajer();
        $branch = Branch::factory()->create();
        $category = FixedAssetCategory::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.aktiva-tetap.store'), [
            'branch_id' => $branch->id,
            'fixed_asset_category_id' => $category->id,
            'code' => 'AT-9001',
            'name' => 'Laptop Kantor',
            'acquisition_date' => now()->toDateString(),
            'acquisition_cost' => 5000000,
            'residual_value' => 6000000,
            'useful_life_months' => 36,
            'depreciation_method' => 'garis_lurus',
            'payment_method' => 'tunai',
        ]);

        $response->assertSessionHasErrors(['residual_value']);
        $this->assertDatabaseCount('fixed_assets', 0);
    }

    public function test_role_without_permission_cannot_create_fixed_asset(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $branch = Branch::factory()->create();
        $category = FixedAssetCategory::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.aktiva-tetap.store'), [
            'branch_id' => $branch->id,
            'fixed_asset_category_id' => $category->id,
            'code' => 'AT-9002',
            'name' => 'Laptop Kantor',
            'acquisition_date' => now()->toDateString(),
            'acquisition_cost' => 5000000,
            'residual_value' => 500000,
            'useful_life_months' => 36,
            'depreciation_method' => 'garis_lurus',
            'payment_method' => 'tunai',
        ]);

        $response->assertForbidden();
    }

    public function test_full_flow_manajer_submits_and_different_manajer_approves(): void
    {
        ApprovalThreshold::query()->create(['module' => 'aktiva_tetap', 'threshold_amount' => 1000000]);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $creator = $this->manajer();
        $approver = $this->manajer();
        $branch = Branch::factory()->create();
        $category = FixedAssetCategory::factory()->create();

        $storeResponse = $this->actingAs($creator)->post(route('admin.aktiva-tetap.store'), [
            'branch_id' => $branch->id,
            'fixed_asset_category_id' => $category->id,
            'code' => 'AT-9003',
            'name' => 'Laptop Kantor',
            'acquisition_date' => now()->toDateString(),
            'acquisition_cost' => 12000000,
            'residual_value' => 1000000,
            'useful_life_months' => 36,
            'depreciation_method' => 'garis_lurus',
            'payment_method' => 'tunai',
        ]);
        $storeResponse->assertRedirect(route('admin.aktiva-tetap.index'));

        $asset = FixedAsset::query()->firstOrFail();
        $this->assertEquals('menunggu_approval', $asset->status);

        $approveResponse = $this->actingAs($approver)->post(route('admin.aktiva-tetap.decide', $asset), [
            'decision' => 'setuju',
        ]);
        $approveResponse->assertRedirect(route('admin.aktiva-tetap.index'));

        $this->assertEquals('aktif', $asset->fresh()->status);
    }
}
