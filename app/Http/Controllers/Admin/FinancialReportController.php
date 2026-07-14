<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportFinancialReportRequest;
use App\Jobs\GenerateFinancialReport;
use App\Models\Branch;
use App\Models\FinancialReportExport;
use App\Services\Reporting\FinancialReportEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportController extends Controller
{
    public function __construct(private readonly FinancialReportEngine $engine) {}

    public function index(Request $request): View
    {
        $this->authorize('laporan_keuangan.read');

        $branchId = $this->resolveBranchId($request);
        $basis = $request->input('basis', 'sak_ep');
        $asOfDate = $request->input('as_of_date', now()->toDateString());
        $periodStart = $request->input('period_start', now()->startOfMonth()->toDateString());
        $periodEnd = $request->input('period_end', now()->toDateString());

        return view('admin.laporan-keuangan.index', [
            'basis' => $basis,
            'asOfDate' => $asOfDate,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'neraca' => $this->engine->neraca($branchId, $asOfDate, $basis),
            'labaRugi' => $this->engine->labaRugi($branchId, $periodStart, $periodEnd, $basis),
            'arusKas' => $this->engine->arusKas($branchId, $periodStart, $periodEnd),
            'calk' => $this->engine->calk($branchId, $asOfDate, $basis),
            'branches' => $this->availableBranches($request),
            'selectedBranchId' => $branchId,
            'isConsolidated' => $branchId === null,
        ]);
    }

    public function export(ExportFinancialReportRequest $request): RedirectResponse
    {
        $branchId = $this->resolveBranchId($request);

        $export = FinancialReportExport::query()->create([
            'report_kind' => $request->validated('report_kind'),
            'basis' => $request->validated('basis'),
            'branch_id' => $branchId,
            'as_of_date' => $request->validated('as_of_date'),
            'period_start' => $request->validated('period_start'),
            'period_end' => $request->validated('period_end'),
            'format' => $request->validated('format'),
            'status' => 'menunggu',
            'requested_by' => $request->user()->id,
        ]);

        GenerateFinancialReport::dispatch($export->id);

        return redirect()
            ->route('admin.laporan-keuangan.exports')
            ->with('status', 'Laporan sedang diproses — akan muncul di daftar Ekspor setelah selesai.');
    }

    public function exports(): View
    {
        $this->authorize('laporan_keuangan.read');

        return view('admin.laporan-keuangan.exports', [
            'exports' => FinancialReportExport::query()
                ->where('requested_by', auth()->id())
                ->latest()
                ->limit(30)
                ->get(),
        ]);
    }

    public function download(FinancialReportExport $export): StreamedResponse
    {
        $this->authorize('laporan_keuangan.read');

        abort_unless($export->isReady() && $export->requested_by === auth()->id(), 404);

        return Storage::disk('local')->download($export->file_path);
    }

    private function resolveBranchId(Request $request): ?int
    {
        $allowed = $request->user()->allowedBranchIds();
        $requested = $request->integer('branch_id') ?: null;

        if ($allowed === null) {
            return $requested;
        }

        if ($requested !== null && ! in_array($requested, $allowed, true)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        return $requested ?? ($allowed[0] ?? null);
    }

    private function availableBranches(Request $request)
    {
        $allowed = $request->user()->allowedBranchIds();

        return $allowed === null
            ? Branch::query()->where('is_active', true)->get()
            : Branch::query()->where('is_active', true)->whereIn('id', $allowed)->get();
    }
}
