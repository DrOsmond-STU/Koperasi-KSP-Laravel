<?php

namespace App\Jobs;

use App\Exports\ReportBuilderExport;
use App\Models\ReportExport;
use App\Services\Reporting\ReportBuilderService;
use App\Services\Reporting\ReportTypeRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * PRD §17 RPT-12 — every export runs on the queue, never inline in a
 * request, so a large/cross-branch report never blocks other users.
 */
class ExportReportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $reportExportId) {}

    public function handle(ReportBuilderService $builder): void
    {
        $export = ReportExport::query()->find($this->reportExportId);

        if ($export === null) {
            return;
        }

        $export->update(['status' => 'memproses']);

        try {
            $rows = $builder->generate($export->report_type, $export->columns, $export->filters ?? []);
            $labels = ReportTypeRegistry::columnsFor($export->report_type);
            $headings = array_map(fn (string $column) => $labels[$column] ?? $column, $export->columns);
            $orderedRows = $rows->map(fn (array $row) => array_map(fn (string $column) => $row[$column] ?? null, $export->columns))->all();

            $filename = "laporan-kustom-{$export->id}.{$export->format}";
            $path = "report-exports/{$filename}";

            if ($export->format === 'pdf') {
                $html = view('admin.laporan-kustom.pdf', [
                    'headings' => $headings,
                    'rows' => $orderedRows,
                    'title' => ReportTypeRegistry::labelFor($export->report_type),
                ])->render();

                Storage::disk('local')->put($path, Pdf::loadHTML($html)->output());
            } else {
                Excel::store(new ReportBuilderExport($headings, $orderedRows), $path, 'local');
            }

            $export->update([
                'status' => 'selesai',
                'file_path' => $path,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $export->update([
                'status' => 'gagal',
                'error_message' => $exception->getMessage(),
            ]);
        }
    }
}
