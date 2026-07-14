<?php

namespace Tests\Feature\FixedAsset;

use App\Exceptions\FixedAsset\FixedAssetDisposalException;
use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\User;
use App\Services\FixedAsset\DepreciationEngine;
use App\Services\FixedAsset\FixedAssetDisposalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md DEP-05: Nilai Perolehan & Akumulasi Penyusutan
 * dihapus dari neraca; selisih nilai jual vs nilai buku jadi Laba/Rugi
 * Pelepasan; aset dikecualikan dari batch penyusutan berikutnya.
 */
class FixedAssetDisposalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    private function category(): FixedAssetCategory
    {
        return FixedAssetCategory::factory()->create([
            'coa_asset_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_accumulated_depreciation_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_depreciation_expense_account_id' => ChartOfAccount::factory()->create()->id,
        ]);
    }

    public function test_disposal_with_sale_above_book_value_records_a_gain(): void
    {
        $category = $this->category();
        $asset = FixedAsset::factory()->create([
            'fixed_asset_category_id' => $category->id,
            'acquisition_cost' => 12000000,
            'residual_value' => 1200000,
        ]);
        $user = User::factory()->create();
        app(DepreciationEngine::class)->processAsset($asset, '2026-08', $user->id); // accumulate some depreciation

        $bookValue = (float) $asset->bookValue();
        $saleAmount = $bookValue + 500000;

        $disposal = app(FixedAssetDisposalService::class)->dispose($asset, 'dijual', (string) $saleAmount, $user->id);

        $this->assertTrue($disposal->isGain());
        $this->assertEquals('500000.00', $disposal->gain_loss_amount);
        $this->assertEquals('dijual', $asset->fresh()->status);

        $gainAccountId = ChartOfAccount::query()->where('code', '4903')->value('id');
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $disposal->journal_entry_id,
            'chart_of_account_id' => $gainAccountId,
            'credit' => '500000.00',
        ]);
    }

    public function test_disposal_with_sale_below_book_value_records_a_loss(): void
    {
        $category = $this->category();
        $asset = FixedAsset::factory()->create([
            'fixed_asset_category_id' => $category->id,
            'acquisition_cost' => 12000000,
            'residual_value' => 1200000,
        ]);
        $user = User::factory()->create();

        $bookValue = (float) $asset->bookValue();
        $saleAmount = $bookValue - 300000;

        $disposal = app(FixedAssetDisposalService::class)->dispose($asset, 'dijual', (string) $saleAmount, $user->id);

        $this->assertFalse($disposal->isGain());
        $this->assertEquals('-300000.00', $disposal->gain_loss_amount);

        $lossAccountId = ChartOfAccount::query()->where('code', '5904')->value('id');
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $disposal->journal_entry_id,
            'chart_of_account_id' => $lossAccountId,
            'debit' => '300000.00',
        ]);
    }

    public function test_dihapusbukukan_with_no_sale_books_full_book_value_as_loss(): void
    {
        $category = $this->category();
        $asset = FixedAsset::factory()->create([
            'fixed_asset_category_id' => $category->id,
            'acquisition_cost' => 12000000,
            'residual_value' => 1200000,
        ]);
        $user = User::factory()->create();

        $disposal = app(FixedAssetDisposalService::class)->dispose($asset, 'dihapusbukukan', '0', $user->id);

        $this->assertEquals(bcmul('-1', (string) $asset->acquisition_cost, 2), $disposal->gain_loss_amount);
        $this->assertEquals('dihapusbukukan', $asset->fresh()->status);
    }

    public function test_hilang_disposal_maps_to_dihapusbukukan_status(): void
    {
        $category = $this->category();
        $asset = FixedAsset::factory()->create(['fixed_asset_category_id' => $category->id]);
        $user = User::factory()->create();

        app(FixedAssetDisposalService::class)->dispose($asset, 'hilang', '0', $user->id);

        $this->assertEquals('dihapusbukukan', $asset->fresh()->status);
    }

    public function test_disposed_asset_is_excluded_from_next_depreciation_batch(): void
    {
        $category = $this->category();
        $asset = FixedAsset::factory()->create([
            'fixed_asset_category_id' => $category->id,
            'acquisition_cost' => 12000000,
            'residual_value' => 1200000,
            'useful_life_months' => 24,
            'depreciation_method' => 'garis_lurus',
        ]);
        $user = User::factory()->create();

        app(FixedAssetDisposalService::class)->dispose($asset, 'dihapusbukukan', '0', $user->id);

        $result = app(DepreciationEngine::class)->run('2026-09', $user->id);

        $this->assertEquals(0, $result['created']);
        $this->assertDatabaseCount('fixed_asset_depreciation_entries', 0);
    }

    public function test_disposing_a_non_active_asset_is_rejected(): void
    {
        $category = $this->category();
        $asset = FixedAsset::factory()->create([
            'fixed_asset_category_id' => $category->id,
            'status' => 'dijual',
        ]);
        $user = User::factory()->create();

        $this->expectException(FixedAssetDisposalException::class);
        app(FixedAssetDisposalService::class)->dispose($asset, 'dihapusbukukan', '0', $user->id);
    }
}
