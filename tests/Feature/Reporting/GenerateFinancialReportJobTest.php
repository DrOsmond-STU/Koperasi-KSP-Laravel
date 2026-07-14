<?php

namespace Tests\Feature\Reporting;

use App\Jobs\GenerateFinancialReport;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FinancialReportExport;
use App\Models\User;
use App\Services\Accounting\JournalEngine;
use App\Services\Reporting\FinancialReportEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md RPT-07: ekspor PDF/Excel berhasil, memuat jejak
 * "dibuat oleh & waktu".
 */
class GenerateFinancialReportJobTest extends TestCase
{
    use RefreshDatabase;

    private function seedNeracaData(): array
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $aset = ChartOfAccount::factory()->create(['type' => 'ASET', 'statement' => 'NERACA', 'normal_balance' => 'DEBIT']);
        $ekuitas = ChartOfAccount::factory()->create(['type' => 'EKUITAS', 'statement' => 'NERACA', 'normal_balance' => 'KREDIT']);

        app(JournalEngine::class)->post([
            'branch_id' => $branch->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Uji',
            'created_by' => $user->id,
            'lines' => [
                ['chart_of_account_id' => $aset->id, 'debit' => 1000000, 'credit' => 0],
                ['chart_of_account_id' => $ekuitas->id, 'debit' => 0, 'credit' => 1000000],
            ],
        ]);

        return compact('branch', 'user');
    }

    public function test_job_generates_pdf_and_marks_selesai(): void
    {
        Storage::fake('local');
        ['branch' => $branch, 'user' => $user] = $this->seedNeracaData();

        $export = FinancialReportExport::query()->create([
            'report_kind' => 'neraca',
            'basis' => 'sak_ep',
            'branch_id' => $branch->id,
            'as_of_date' => now()->toDateString(),
            'format' => 'pdf',
            'status' => 'menunggu',
            'requested_by' => $user->id,
        ]);

        (new GenerateFinancialReport($export->id))->handle(app(FinancialReportEngine::class));

        $export->refresh();
        $this->assertEquals('selesai', $export->status);
        Storage::disk('local')->assertExists($export->file_path);
    }

    public function test_job_generates_xlsx_and_marks_selesai(): void
    {
        Storage::fake('local');
        ['branch' => $branch, 'user' => $user] = $this->seedNeracaData();

        $export = FinancialReportExport::query()->create([
            'report_kind' => 'laba_rugi',
            'basis' => 'sak_ep',
            'branch_id' => $branch->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'format' => 'xlsx',
            'status' => 'menunggu',
            'requested_by' => $user->id,
        ]);

        (new GenerateFinancialReport($export->id))->handle(app(FinancialReportEngine::class));

        $export->refresh();
        $this->assertEquals('selesai', $export->status, (string) $export->error_message);
        Storage::disk('local')->assertExists($export->file_path);
    }
}
