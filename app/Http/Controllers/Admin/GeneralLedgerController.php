<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Services\Accounting\GeneralLedgerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GeneralLedgerController extends Controller
{
    public function __construct(private readonly GeneralLedgerService $ledgerService) {}

    public function index(Request $request): View
    {
        $this->authorize('jurnal.read');

        $branchId = $this->resolveBranchId($request);
        $accountId = $request->integer('chart_of_account_id') ?: null;
        $periodStart = $request->input('period_start', now()->startOfMonth()->toDateString());
        $periodEnd = $request->input('period_end', now()->toDateString());

        $account = $accountId !== null ? ChartOfAccount::query()->find($accountId) : null;

        return view('admin.jurnal-buku-besar', [
            'accounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
            'selectedAccount' => $account,
            'lines' => $account !== null ? $this->ledgerService->linesFor($account, $branchId, $periodStart, $periodEnd) : collect(),
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'branches' => $this->availableBranches($request),
            'selectedBranchId' => $branchId,
            'isConsolidated' => $branchId === null,
        ]);
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
