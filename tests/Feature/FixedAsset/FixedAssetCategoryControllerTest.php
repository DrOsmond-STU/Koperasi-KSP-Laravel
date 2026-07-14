<?php

namespace Tests\Feature\FixedAsset;

use App\Models\ChartOfAccount;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md AUTH-14: kategori aktiva tetap tidak bisa aktif
 * tanpa mapping COA Aset/Akumulasi Penyusutan/Beban Penyusutan yang valid.
 */
class FixedAssetCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        return $user;
    }

    public function test_creating_category_without_coa_mapping_is_rejected(): void
    {
        $user = $this->manajer();

        $response = $this->actingAs($user)->post(route('admin.master.fixed-asset-categories.store'), [
            'code' => 'KAT-001',
            'name' => 'Peralatan Kantor',
            'default_depreciation_method' => 'garis_lurus',
            'default_useful_life_months' => 48,
        ]);

        $response->assertSessionHasErrors([
            'coa_asset_account_id',
            'coa_accumulated_depreciation_account_id',
            'coa_depreciation_expense_account_id',
        ]);
        $this->assertDatabaseCount('fixed_asset_categories', 0);
    }

    public function test_creating_category_with_valid_postable_accounts_succeeds(): void
    {
        $user = $this->manajer();

        $asset = ChartOfAccount::factory()->create();
        $accum = ChartOfAccount::factory()->create();
        $expense = ChartOfAccount::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.master.fixed-asset-categories.store'), [
            'code' => 'KAT-002',
            'name' => 'Peralatan Kantor',
            'default_depreciation_method' => 'garis_lurus',
            'default_useful_life_months' => 48,
            'coa_asset_account_id' => $asset->id,
            'coa_accumulated_depreciation_account_id' => $accum->id,
            'coa_depreciation_expense_account_id' => $expense->id,
        ]);

        $response->assertRedirect(route('admin.master.fixed-asset-categories.index'));
        $this->assertDatabaseHas('fixed_asset_categories', ['code' => 'KAT-002', 'is_active' => 1]);
    }
}
