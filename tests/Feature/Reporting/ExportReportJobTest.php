<?php

namespace Tests\Feature\Reporting;

use App\Jobs\ExportReportJob;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Inventory\StockLedgerEngine;
use App\Services\Reporting\ReportBuilderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md RPT-12: laporan diproses async (queue), tidak
 * memblokir; hasil (PDF/XLSX) tersedia setelah job selesai.
 */
class ExportReportJobTest extends TestCase
{
    use RefreshDatabase;

    private function seedStockMovement(): array
    {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $user = User::factory()->create();
        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id, date: Carbon::parse('2026-03-05'));

        return compact('branch', 'product', 'user');
    }

    public function test_job_generates_pdf_and_marks_export_selesai(): void
    {
        Storage::fake('local');
        ['branch' => $branch, 'product' => $product, 'user' => $user] = $this->seedStockMovement();

        $export = ReportExport::query()->create([
            'report_type' => 'persediaan_kartu',
            'columns' => ['transaction_date', 'qty_in', 'running_qty'],
            'filters' => ['product_id' => $product->id, 'branch_id' => $branch->id, 'period_start' => '2026-03-01', 'period_end' => '2026-03-31'],
            'format' => 'pdf',
            'status' => 'menunggu',
            'requested_by' => $user->id,
        ]);

        (new ExportReportJob($export->id))->handle(app(ReportBuilderService::class));

        $export->refresh();
        $this->assertEquals('selesai', $export->status);
        $this->assertNotNull($export->file_path);
        Storage::disk('local')->assertExists($export->file_path);
    }

    public function test_job_generates_xlsx_and_marks_export_selesai(): void
    {
        Storage::fake('local');
        ['branch' => $branch, 'product' => $product, 'user' => $user] = $this->seedStockMovement();

        $export = ReportExport::query()->create([
            'report_type' => 'persediaan_kartu',
            'columns' => ['transaction_date', 'qty_in', 'running_qty'],
            'filters' => ['product_id' => $product->id, 'branch_id' => $branch->id, 'period_start' => '2026-03-01', 'period_end' => '2026-03-31'],
            'format' => 'xlsx',
            'status' => 'menunggu',
            'requested_by' => $user->id,
        ]);

        (new ExportReportJob($export->id))->handle(app(ReportBuilderService::class));

        $export->refresh();
        $this->assertEquals('selesai', $export->status);
        Storage::disk('local')->assertExists($export->file_path);
    }

    public function test_job_marks_export_gagal_on_error(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $export = ReportExport::query()->create([
            'report_type' => 'persediaan_kartu',
            'columns' => ['transaction_date'],
            'filters' => [], // missing required product_id/period -> will error inside the service
            'format' => 'pdf',
            'status' => 'menunggu',
            'requested_by' => $user->id,
        ]);

        (new ExportReportJob($export->id))->handle(app(ReportBuilderService::class));

        $this->assertEquals('gagal', $export->fresh()->status);
    }
}
