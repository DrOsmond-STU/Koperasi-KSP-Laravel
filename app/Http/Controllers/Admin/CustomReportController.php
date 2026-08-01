<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Reporting\ReportBuilderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateCustomReportRequest;
use App\Jobs\ExportReportJob;
use App\Models\Branch;
use App\Models\FixedAsset;
use App\Models\Product;
use App\Models\ReportExport;
use App\Services\Reporting\ReportBuilderService;
use App\Services\Reporting\ReportTypeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomReportController extends Controller
{
    public function __construct(private readonly ReportBuilderService $reportBuilder) {}

    public function index(): View
    {
        $this->authorize('laporan_kustom.read');

        return view('admin.laporan-kustom.index', [
            'reportTypes' => ReportTypeRegistry::definitions(),
            'templates' => $this->reportBuilder->templatesAvailableTo(auth()->user()),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
            'fixedAssets' => FixedAsset::query()->where('status', 'aktif')->orderBy('code')->get(),
        ]);
    }

    public function generate(GenerateCustomReportRequest $request): RedirectResponse|View
    {
        $reportType = $request->validated('report_type');
        $columns = $request->validated('columns');
        $filters = $request->validated('filters') ?? [];

        try {
            if ($request->filled('save_as_template')) {
                $this->reportBuilder->saveTemplate(
                    $request->validated('save_as_template'),
                    $reportType,
                    $columns,
                    $filters,
                    $request->user(),
                );
            }

            if ($request->filled('export_format')) {
                $export = ReportExport::query()->create([
                    'report_type' => $reportType,
                    'columns' => $columns,
                    'filters' => $filters,
                    'format' => $request->validated('export_format'),
                    'status' => 'menunggu',
                    'requested_by' => $request->user()->id,
                ]);

                ExportReportJob::dispatch($export->id);

                return redirect()
                    ->route('admin.laporan-kustom.exports')
                    ->with('status', 'Laporan sedang diproses — Anda akan melihatnya di daftar Ekspor setelah selesai.');
            }

            $rows = $this->reportBuilder->generate($reportType, $columns, $filters);
        } catch (ReportBuilderException $exception) {
            return redirect()->route('admin.laporan-kustom.index')->with('error', $exception->getMessage());
        }

        return view('admin.laporan-kustom.preview', [
            'columns' => $columns,
            'labels' => ReportTypeRegistry::columnsFor($reportType),
            'rows' => $rows,
        ]);
    }

    public function exports(): View
    {
        $this->authorize('laporan_kustom.read');

        return view('admin.laporan-kustom.exports', [
            'exports' => ReportExport::query()
                ->where('requested_by', auth()->id())
                ->latest()
                ->limit(30)
                ->get(),
        ]);
    }

    public function download(ReportExport $export): StreamedResponse
    {
        $this->authorize('laporan_kustom.read');

        abort_unless($export->isReady() && $export->requested_by === auth()->id(), 404);

        return Storage::disk('local')->download($export->file_path);
    }
}
