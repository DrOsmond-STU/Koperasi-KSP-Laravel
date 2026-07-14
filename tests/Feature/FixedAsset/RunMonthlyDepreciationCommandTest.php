<?php

namespace Tests\Feature\FixedAsset;

use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunMonthlyDepreciationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_batch_using_admin_sistem_as_system_actor(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin_sistem');

        $category = FixedAssetCategory::factory()->create([
            'coa_asset_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_accumulated_depreciation_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_depreciation_expense_account_id' => ChartOfAccount::factory()->create()->id,
        ]);
        FixedAsset::factory()->create([
            'fixed_asset_category_id' => $category->id,
            'status' => 'aktif',
            'acquisition_cost' => 12000000,
            'residual_value' => 1200000,
            'useful_life_months' => 24,
            'depreciation_method' => 'garis_lurus',
        ]);

        $this->artisan('depreciation:run', ['period' => '2026-08'])
            ->assertExitCode(0);

        $this->assertDatabaseCount('fixed_asset_depreciation_entries', 1);
        $this->assertDatabaseHas('fixed_asset_depreciation_entries', ['created_by' => $admin->id]);
    }

    public function test_command_fails_without_an_admin_sistem_user(): void
    {
        $this->artisan('depreciation:run')->assertExitCode(1);
    }
}
