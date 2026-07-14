<?php

namespace Tests\Feature\Reporting;

use App\Exceptions\Reporting\ReportBuilderException;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\FixedAsset\DepreciationEngine;
use App\Services\Inventory\StockLedgerEngine;
use App\Services\Reporting\ReportBuilderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md RPT-10 (kolom di luar whitelist ditolak server),
 * RPT-11 (template tersimpan & dipakai ulang konsisten), and RPT-13
 * (Kartu Persediaan & Kartu Penyusutan lewat Report Builder sesuai
 * PRD §13.7/§14.3).
 */
class ReportBuilderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_rejects_columns_outside_whitelist(): void
    {
        $this->expectException(ReportBuilderException::class);

        app(ReportBuilderService::class)->generate('persediaan_kartu', ['transaction_date', 'created_by_secret_column'], [
            'product_id' => 1,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
        ]);
    }

    public function test_generate_rejects_unknown_report_type(): void
    {
        $this->expectException(ReportBuilderException::class);

        app(ReportBuilderService::class)->generate('laporan_rahasia', ['x'], []);
    }

    public function test_save_template_rejects_invalid_columns(): void
    {
        $user = User::factory()->create();

        $this->expectException(ReportBuilderException::class);
        app(ReportBuilderService::class)->saveTemplate('Template X', 'persediaan_kartu', ['kolom_tidak_ada'], [], $user);
    }

    public function test_save_and_reuse_template_is_consistent(): void
    {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $user = User::factory()->create();
        $engine = app(StockLedgerEngine::class);
        $engine->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id, date: Carbon::parse('2026-01-05'));

        $service = app(ReportBuilderService::class);
        $template = $service->saveTemplate(
            'Kartu Persediaan Januari',
            'persediaan_kartu',
            ['transaction_date', 'qty_in', 'running_qty'],
            ['product_id' => $product->id, 'branch_id' => $branch->id, 'period_start' => '2026-01-01', 'period_end' => '2026-01-31'],
            $user,
        );

        $this->assertDatabaseHas('report_templates', ['name' => 'Kartu Persediaan Januari']);

        $rows = $service->generate($template->report_type, $template->columns, $template->filters);

        $this->assertCount(1, $rows);
        $this->assertEqualsCanonicalizing(['transaction_date', 'qty_in', 'running_qty'], array_keys($rows->first()));
    }

    public function test_persediaan_kartu_report_reflects_stock_ledger_movements(): void
    {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $user = User::factory()->create();
        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id, date: Carbon::parse('2026-02-05'));

        $rows = app(ReportBuilderService::class)->generate('persediaan_kartu', ['qty_in', 'running_qty'], [
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
        ]);

        $this->assertEquals('10.0000', $rows->first()['qty_in']);
        $this->assertEquals('10.0000', $rows->first()['running_qty']);
    }

    public function test_aktiva_tetap_kartu_penyusutan_report_reflects_depreciation_entries(): void
    {
        $category = FixedAssetCategory::factory()->create([
            'coa_asset_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_accumulated_depreciation_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_depreciation_expense_account_id' => ChartOfAccount::factory()->create()->id,
        ]);
        $asset = FixedAsset::factory()->create([
            'fixed_asset_category_id' => $category->id,
            'acquisition_cost' => 12000000,
            'residual_value' => 1200000,
            'useful_life_months' => 24,
            'depreciation_method' => 'garis_lurus',
        ]);
        $user = User::factory()->create();
        app(DepreciationEngine::class)->processAsset($asset, '2026-08', $user->id);

        $rows = app(ReportBuilderService::class)->generate('aktiva_tetap_kartu_penyusutan', ['period', 'depreciation_amount'], [
            'fixed_asset_id' => $asset->id,
        ]);

        $this->assertCount(1, $rows);
        $this->assertEquals('2026-08', $rows->first()['period']);
        $this->assertEquals('450000.00', $rows->first()['depreciation_amount']);
    }
}
