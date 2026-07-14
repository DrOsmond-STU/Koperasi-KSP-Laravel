<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\FixedAsset;
use App\Services\FixedAsset\FixedAssetReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FixedAssetReportController extends Controller
{
    public function __construct(private readonly FixedAssetReportService $reportService) {}

    public function daftar(Request $request): View
    {
        $this->authorize('laporan_kustom.read');

        $branchId = $this->resolveBranchId($request);

        return view('admin.aktiva-tetap.laporan.daftar', [
            'rows' => $this->reportService->daftarAktivaTetap($branchId),
            'branches' => $this->availableBranches($request),
            'selectedBranchId' => $branchId,
            'isConsolidated' => $branchId === null,
        ]);
    }

    public function kartuPenyusutan(Request $request): View
    {
        $this->authorize('laporan_kustom.read');

        $assetId = $request->integer('fixed_asset_id') ?: null;

        return view('admin.aktiva-tetap.laporan.kartu-penyusutan', [
            'assets' => FixedAsset::query()->orderBy('code')->get(),
            'selectedAssetId' => $assetId,
            'entries' => $assetId !== null ? $this->reportService->kartuPenyusutan($assetId) : collect(),
        ]);
    }

    public function ringkasanPenyusutan(Request $request): View
    {
        $this->authorize('laporan_kustom.read');

        $branchId = $this->resolveBranchId($request);
        $period = $request->input('period', now()->format('Y-m'));

        return view('admin.aktiva-tetap.laporan.ringkasan-penyusutan', [
            'rows' => $this->reportService->ringkasanPenyusutanPerPeriode($period, $branchId),
            'period' => $period,
            'branches' => $this->availableBranches($request),
            'selectedBranchId' => $branchId,
            'isConsolidated' => $branchId === null,
        ]);
    }

    public function pelepasan(Request $request): View
    {
        $this->authorize('laporan_kustom.read');

        $branchId = $this->resolveBranchId($request);

        return view('admin.aktiva-tetap.laporan.pelepasan', [
            'rows' => $this->reportService->laporanPelepasan($branchId),
            'branches' => $this->availableBranches($request),
            'selectedBranchId' => $branchId,
            'isConsolidated' => $branchId === null,
        ]);
    }

    /**
     * Mirrors DashboardController::resolveBranchId() — same Cabang Scope
     * enforcement pattern (PRD §6).
     */
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
