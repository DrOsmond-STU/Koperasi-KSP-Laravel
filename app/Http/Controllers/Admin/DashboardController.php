<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Calendar\CalendarService;
use App\Services\Dashboard\CashBankDashboardService;
use App\Services\Dashboard\MainDashboardService;
use App\Services\Dashboard\RatDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    use GeneratesPrintPdf;

    public function __construct(
        private readonly MainDashboardService $mainDashboard,
        private readonly CashBankDashboardService $cashBankDashboard,
        private readonly RatDashboardService $ratDashboard,
        private readonly CalendarService $calendar,
    ) {}

    public function main(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);

        return view('admin.dashboard.index', [
            'summary' => $this->mainDashboard->summary($branchId),
            'branches' => $this->availableBranches($request),
            'selectedBranchId' => $branchId,
            'isConsolidated' => $branchId === null,
        ]);
    }

    public function kasBank(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);

        return view('admin.dashboard.kas-bank', [
            'summary' => $this->cashBankDashboard->summary($branchId),
            'branches' => $this->availableBranches($request),
            'selectedBranchId' => $branchId,
            'isConsolidated' => $branchId === null,
        ]);
    }

    public function rat(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);

        return view('admin.dashboard.rat', [
            'summary' => $this->ratDashboard->summary($branchId),
            'branches' => $this->availableBranches($request),
            'selectedBranchId' => $branchId,
            'isConsolidated' => $branchId === null,
        ]);
    }

    public function kalender(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);
        $year = $request->integer('year') ?: (int) now()->format('Y');
        $month = $request->integer('month') ?: (int) now()->format('n');

        return view('admin.dashboard.kalender', [
            'itemsByDate' => $this->calendar->monthView($year, $month, $branchId),
            'year' => $year,
            'month' => $month,
            'branches' => $this->availableBranches($request),
            'selectedBranchId' => $branchId,
            'isConsolidated' => $branchId === null,
        ]);
    }

    /**
     * Laporan Harian Kas/Bank — satu tanggal (per PRD user request: "dibuat
     * pertanggal saja"), ttd Teller + Pengurus dari App\Models\DocumentSignatureSlot.
     */
    public function printKasBankHarian(Request $request): Response
    {
        $this->authorize('jurnal.read');

        $branchId = $this->resolveBranchId($request);
        $date = $request->string('tanggal')->value() ?: now()->toDateString();

        $pdf = $this->renderPrintPdf('prints.laporan.kas-bank-harian', [
            'report' => $this->cashBankDashboard->dailyTransactions($date, $branchId),
            'branch' => $branchId ? Branch::query()->find($branchId) : null,
        ]);

        return $pdf->download('laporan-kas-bank-'.$date.'.pdf');
    }

    /**
     * Resolves the branch to filter by, enforcing Cabang Scope (PRD §6):
     * - All Branch (`allowedBranchIds() === null`): may request a specific
     *   branch_id, or omit it for consolidated view.
     * - Single/Multiple: the requested branch_id must be in the allowed
     *   list; defaults to the first allowed branch if omitted (no
     *   "consolidated" option for these scopes).
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
