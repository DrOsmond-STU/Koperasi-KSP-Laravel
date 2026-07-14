<?php

namespace Tests\Feature\FixedAsset;

use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\User;
use App\Services\FixedAsset\DepreciationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md DEP-01 (batch bulanan, satu entri per aset Aktif),
 * DEP-02 (idempotent per periode), DEP-03 (berhenti di nilai residu), and
 * DEP-04 (formula Garis Lurus vs Saldo Menurun).
 */
class DepreciationEngineTest extends TestCase
{
    use RefreshDatabase;

    private function category(): FixedAssetCategory
    {
        return FixedAssetCategory::factory()->create([
            'coa_asset_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_accumulated_depreciation_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_depreciation_expense_account_id' => ChartOfAccount::factory()->create()->id,
        ]);
    }

    public function test_batch_creates_one_entry_per_active_asset_with_journal(): void
    {
        $category = $this->category();
        $asset = FixedAsset::factory()->create([
            'fixed_asset_category_id' => $category->id,
            'status' => 'aktif',
            'acquisition_cost' => 12000000,
            'residual_value' => 1200000,
            'useful_life_months' => 24,
            'depreciation_method' => 'garis_lurus',
        ]);
        $user = User::factory()->create();

        $result = app(DepreciationEngine::class)->run('2026-08', $user->id);

        $this->assertEquals(1, $result['created']);
        $this->assertDatabaseCount('fixed_asset_depreciation_entries', 1);

        $entry = $asset->depreciationEntries()->first();
        $this->assertEquals('450000.00', $entry->depreciation_amount);
        $this->assertEquals('11550000.00', $entry->closing_book_value);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->journal_entry_id,
            'chart_of_account_id' => $category->coa_depreciation_expense_account_id,
            'debit' => '450000.00',
        ]);
    }

    public function test_rerunning_same_period_does_not_duplicate_entries(): void
    {
        $category = $this->category();
        FixedAsset::factory()->create([
            'fixed_asset_category_id' => $category->id,
            'status' => 'aktif',
            'acquisition_cost' => 12000000,
            'residual_value' => 1200000,
            'useful_life_months' => 24,
            'depreciation_method' => 'garis_lurus',
        ]);
        $user = User::factory()->create();

        $engine = app(DepreciationEngine::class);
        $first = $engine->run('2026-08', $user->id);
        $second = $engine->run('2026-08', $user->id);

        $this->assertEquals(1, $first['created']);
        $this->assertEquals(0, $second['created']);
        $this->assertEquals(1, $second['skipped']);
        $this->assertDatabaseCount('fixed_asset_depreciation_entries', 1);
    }

    public function test_depreciation_stops_once_book_value_reaches_residual(): void
    {
        $category = $this->category();
        $asset = FixedAsset::factory()->create([
            'fixed_asset_category_id' => $category->id,
            'status' => 'aktif',
            'acquisition_cost' => 1000000,
            'residual_value' => 900000,
            'useful_life_months' => 1,
            'depreciation_method' => 'garis_lurus',
        ]);
        $user = User::factory()->create();
        $engine = app(DepreciationEngine::class);

        $engine->run('2026-08', $user->id);
        $result = $engine->run('2026-09', $user->id);

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertEquals('900000.00', $asset->bookValue());
    }

    public function test_garis_lurus_and_saldo_menurun_use_different_formulas(): void
    {
        $category = $this->category();
        $user = User::factory()->create();
        $engine = app(DepreciationEngine::class);

        $straightLine = FixedAsset::factory()->create([
            'fixed_asset_category_id' => $category->id,
            'status' => 'aktif',
            'acquisition_cost' => 12000000,
            'residual_value' => 1200000,
            'useful_life_months' => 24,
            'depreciation_method' => 'garis_lurus',
        ]);
        $decliningBalance = FixedAsset::factory()->create([
            'fixed_asset_category_id' => $category->id,
            'status' => 'aktif',
            'acquisition_cost' => 12000000,
            'residual_value' => 1200000,
            'useful_life_months' => 24,
            'depreciation_method' => 'saldo_menurun',
            'depreciation_rate_percentage' => 10,
        ]);

        $engine->run('2026-08', $user->id);

        $this->assertEquals('450000.00', $straightLine->depreciationEntries()->first()->depreciation_amount);
        $this->assertEquals('1200000.00', $decliningBalance->depreciationEntries()->first()->depreciation_amount);
    }
}
