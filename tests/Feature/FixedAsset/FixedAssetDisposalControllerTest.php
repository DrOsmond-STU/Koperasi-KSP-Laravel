<?php

namespace Tests\Feature\FixedAsset;

use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedAssetDisposalControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_role_without_permission_cannot_dispose_asset(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');

        $category = FixedAssetCategory::factory()->create([
            'coa_asset_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_accumulated_depreciation_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_depreciation_expense_account_id' => ChartOfAccount::factory()->create()->id,
        ]);
        $asset = FixedAsset::factory()->create(['fixed_asset_category_id' => $category->id]);

        $response = $this->actingAs($user)->post(route('admin.aktiva-tetap.pelepasan.store'), [
            'fixed_asset_id' => $asset->id,
            'disposal_type' => 'dihapusbukukan',
        ]);

        $response->assertForbidden();
    }

    public function test_manajer_can_dispose_an_active_asset(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $category = FixedAssetCategory::factory()->create([
            'coa_asset_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_accumulated_depreciation_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_depreciation_expense_account_id' => ChartOfAccount::factory()->create()->id,
        ]);
        $asset = FixedAsset::factory()->create(['fixed_asset_category_id' => $category->id]);

        $response = $this->actingAs($user)->post(route('admin.aktiva-tetap.pelepasan.store'), [
            'fixed_asset_id' => $asset->id,
            'disposal_type' => 'dihapusbukukan',
        ]);

        $response->assertRedirect(route('admin.aktiva-tetap.pelepasan.create'));
        $this->assertDatabaseCount('fixed_asset_disposals', 1);
    }
}
