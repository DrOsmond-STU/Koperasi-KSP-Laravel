<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Services\Accounting\GeneralLedgerService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class GeneralLedgerController extends Controller
{
    use GeneratesPrintPdf;

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

    /**
     * Cetakan Kartu Buku Besar per Akun — sumber data tunggal
     * GeneralLedgerService::linesFor() supaya angka di layar (index) dan
     * cetakan selalu identik. Landscape dipaksa karena kolom saldo berjalan
     * + debit/kredit tidak nyaman di portrait.
     */
    public function print(Request $request): Response
    {
        $this->authorize('jurnal.read');

        $accountId = $request->integer('chart_of_account_id') ?: null;
        abort_if($accountId === null, 400, 'Pilih akun terlebih dahulu.');

        $account = ChartOfAccount::query()->findOrFail($accountId);
        abort_unless($account->is_postable, 400, 'Akun header tidak dapat dicetak buku besarnya.');

        $branchId = $this->resolveBranchId($request);
        $periodStart = $request->input('period_start', now()->startOfMonth()->toDateString());
        $periodEnd = $request->input('period_end', now()->toDateString());
        $branch = $branchId ? Branch::query()->find($branchId) : null;

        $lines = $this->ledgerService->linesFor($account, $branchId, $periodStart, $periodEnd);
        $openingBalance = $this->ledgerService->balanceFor($account, $branchId, \Illuminate\Support\Carbon::parse($periodStart)->subDay()->toDateString());

        $pdf = $this->renderPrintPdf('prints.laporan.buku-besar', [
            'account' => $account,
            'lines' => $lines,
            'openingBalance' => $openingBalance,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'branchName' => $branch?->name,
            'isConsolidated' => $branchId === null,
        ], orientationOverride: 'landscape');

        $filename = 'buku-besar-'.$account->code.'-'.$periodStart.'_sd_'.$periodEnd.($branchId ? '-cab'.$branchId : '-konsolidasi').'.pdf';

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
