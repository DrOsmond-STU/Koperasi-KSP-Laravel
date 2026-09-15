<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExportFinancialReportRequest;
use App\Jobs\GenerateFinancialReport;
use App\Models\Branch;
use App\Models\FinancialReportExport;
use App\Services\Reporting\FinancialReportEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportController extends Controller
{
    use GeneratesPrintPdf;

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

    /**
     * Cetak Neraca dalam BENTUK SKONTRO (horizontal/T-form): Aset di
     * kolom kiri, Kewajiban+Modal di kolom kanan. Berbeda dari export()
     * async yang menghasilkan bentuk stafel (vertikal), cetakan ini
     * mengembalikan PDF secara langsung ke browser — reuse
     * FinancialReportEngine::neraca() sebagai sumber data tunggal supaya
     * angka di layar (index.blade.php), ekspor stafel, dan cetakan
     * skontro selalu identik. Landscape dipaksa karena dua kolom
     * sisi-sisi tidak muat di portrait.
     *
     * Data dinormalkan ke `$balanceByCode` (map kode akun → saldo) agar
     * blade `neraca-scontro` bisa dipakai bareng oleh laporan berjalan
     * (route ini) dan Saldo Awal (LaporanController::printSaldoAwalNeracaSkontro).
     * Blade menangani hierarki + roll-up dari kode akun tersebut.
     */
    public function printScontro(Request $request): Response
    {
        $this->authorize('laporan_keuangan.read');

        $branchId = $this->resolveBranchId($request);
        $basis = $request->input('basis', 'sak_ep');
        $asOfDate = $request->input('as_of_date', now()->toDateString());
        $branch = $branchId ? Branch::query()->find($branchId) : null;

        $data = $this->engine->neraca($branchId, $asOfDate, $basis);

        // Peta saldo per kode akun. Baris `eliminated` (RAK di konsolidasi)
        // dipaksa 0 sebagaimana yang sudah dilakukan engine, jadi tinggal
        // dipakai apa adanya.
        $balanceByCode = [];
        foreach ($data['rows'] as $row) {
            $balanceByCode[$row['account']->code] = (float) $row['balance'];
        }

        $asOfLabel = Carbon::parse($asOfDate)->translatedFormat('d M Y');

        $pdf = $this->renderPrintPdf('prints.laporan.neraca-scontro', [
            'title' => 'Neraca (Bentuk Skontro)',
            'balanceByCode' => $balanceByCode,
            'meta' => [
                'sub_title' => 'Basis '.strtoupper(str_replace('_', ' ', $basis)),
                'periode' => 'per '.$asOfLabel,
                'cabang' => $branch?->name ?? 'Konsolidasi Seluruh Cabang',
                'petugas' => optional($request->user())->name ?? '-',
                'tgl_cetak' => now(),
            ],
        ], orientationOverride: 'landscape');

        $filename = 'neraca-scontro-'.$asOfDate.($branchId ? '-cab'.$branchId : '-konsolidasi').'.pdf';

        return $pdf->stream($filename);
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
