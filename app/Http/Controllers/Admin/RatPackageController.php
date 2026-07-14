<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Accounting\ShuService;
use App\Services\Dashboard\RatDashboardService;
use App\Services\Reporting\FinancialReportEngine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paket Laporan RAT (PRD §5.4, Task 5.4) — menggabungkan Neraca, Laba Rugi,
 * simulasi SHU, dan checklist kesiapan RAT (RatDashboardService, Task 2.5)
 * menjadi satu dokumen PDF siap dibawa ke Rapat Anggota Tahunan.
 */
class RatPackageController extends Controller
{
    public function __construct(
        private readonly FinancialReportEngine $financialReportEngine,
        private readonly ShuService $shuService,
        private readonly RatDashboardService $ratDashboardService,
    ) {}

    public function download(Request $request): Response
    {
        $this->authorize('rat.compose');

        $branchId = $this->resolveBranchId($request);
        $year = $request->integer('year') ?: (int) now()->format('Y');

        $html = view('admin.rat.paket-pdf', [
            'year' => $year,
            'neraca' => $this->financialReportEngine->neraca($branchId, "{$year}-12-31"),
            'labaRugi' => $this->financialReportEngine->labaRugi($branchId, "{$year}-01-01", "{$year}-12-31"),
            'shu' => $this->shuService->simulate($branchId, $year),
            'ratSummary' => $this->ratDashboardService->summary($branchId, $year),
            'requestedByName' => $request->user()->name,
            'requestedAt' => now()->translatedFormat('d M Y H:i'),
        ])->render();

        return Pdf::loadHTML($html)->download("paket-rat-{$year}.pdf");
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
}
