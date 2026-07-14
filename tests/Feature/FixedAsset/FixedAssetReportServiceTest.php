<?php

namespace Tests\Feature\FixedAsset;

use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\User;
use App\Services\FixedAsset\DepreciationEngine;
use App\Services\FixedAsset\FixedAssetDisposalService;
use App\Services\FixedAsset\FixedAssetReportService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md DEP-06: Kartu Penyusutan per Aset (akumulasi
 * kumulatif & nilai buku berjalan) konsisten dengan Laporan Daftar Aktiva
 * Tetap.
 */
class FixedAssetReportServiceTest extends TestCase
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

    public function test_kartu_penyusutan_matches_daftar_aktiva_tetap(): void
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
        $engine = app(DepreciationEngine::class);
        $engine->processAsset($asset, '2026-08', $user->id);
        $engine->processAsset($asset, '2026-09', $user->id);

        $report = app(FixedAssetReportService::class);

        $kartu = $report->kartuPenyusutan($asset->id);
        $lastEntry = $kartu->last();

        $daftar = $report->daftarAktivaTetap(null)->firstWhere('asset.id', $asset->id);

        $this->assertCount(2, $kartu);
        $this->assertEquals($lastEntry->closing_book_value, $daftar['book_value']);
        $this->assertEquals(
            bcadd((string) $kartu[0]->depreciation_amount, (string) $kartu[1]->depreciation_amount, 2),
            $daftar['accumulated_depreciation'],
        );
    }

    public function test_ringkasan_penyusutan_groups_by_category(): void
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
        app(DepreciationEngine::class)->processAsset($asset, '2026-08', $user->id);

        $rows = app(FixedAssetReportService::class)->ringkasanPenyusutanPerPeriode('2026-08', null);

        $this->assertCount(1, $rows);
        $this->assertEquals($category->name, $rows->first()['category_name']);
        $this->assertEquals('450000.00', $rows->first()['total_depreciation']);
    }

    public function test_laporan_pelepasan_shows_gain_or_loss(): void
    {
        $category = $this->category();
        $asset = FixedAsset::factory()->create([
            'fixed_asset_category_id' => $category->id,
            'acquisition_cost' => 12000000,
            'residual_value' => 1200000,
        ]);
        $user = User::factory()->create();
        app(FixedAssetDisposalService::class)->dispose($asset, 'dijual', '13000000', $user->id);

        $rows = app(FixedAssetReportService::class)->laporanPelepasan(null);

        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->isGain());
        $this->assertEquals('1000000.00', $rows->first()->gain_loss_amount);
    }
}
