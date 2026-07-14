<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Services\Inventory\InventoryReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryReportController extends Controller
{
    public function __construct(private readonly InventoryReportService $reportService) {}

    public function saldo(Request $request): View
    {
        $this->authorize('laporan_kustom.read');

        $branchId = $this->resolveBranchId($request);
        $asOfDate = $request->input('as_of_date', now()->toDateString());

        return view('admin.persediaan.laporan.saldo', [
            'rows' => $this->reportService->saldoPersediaan($branchId, $asOfDate),
            'asOfDate' => $asOfDate,
            'branches' => $this->availableBranches($request),
            'selectedBranchId' => $branchId,
            'isConsolidated' => $branchId === null,
        ]);
    }

    public function kartu(Request $request): View
    {
        $this->authorize('laporan_kustom.read');

        $branchId = $this->resolveBranchId($request);
        $periodStart = $request->input('period_start', now()->startOfMonth()->toDateString());
        $periodEnd = $request->input('period_end', now()->toDateString());
        $productId = $request->integer('product_id') ?: null;

        $ringkasan = null;
        $rincian = collect();

        if ($productId !== null) {
            $ringkasan = $this->reportService->kartuRingkasan($productId, $branchId, $periodStart, $periodEnd);
            $rincian = $this->reportService->kartuRincian($productId, $branchId, $periodStart, $periodEnd);
        }

        return view('admin.persediaan.laporan.kartu', [
            'products' => Product::query()->where('is_active', true)->get(),
            'selectedProductId' => $productId,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'ringkasan' => $ringkasan,
            'rincian' => $rincian,
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
