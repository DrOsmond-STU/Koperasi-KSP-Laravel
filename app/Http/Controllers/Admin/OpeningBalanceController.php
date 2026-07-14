<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\OpeningBalance\OpeningBalanceLockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportOpeningBalanceRequest;
use App\Http\Requests\StoreOpeningBalanceBatchRequest;
use App\Models\Branch;
use App\Models\OpeningBalanceBatch;
use App\Services\OpeningBalance\OpeningBalanceImportService;
use App\Services\OpeningBalance\OpeningBalanceLockService;
use App\Services\OpeningBalance\OpeningBalanceReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OpeningBalanceController extends Controller
{
    private const SUB_MODULES = ['savings', 'loans', 'installments', 'coa'];

    public function __construct(
        private readonly OpeningBalanceImportService $importService,
        private readonly OpeningBalanceReconciliationService $reconciliationService,
        private readonly OpeningBalanceLockService $lockService,
    ) {}

    public function index(): View
    {
        $this->authorize('saldo_awal.read');

        return view('admin.saldo-awal.index', [
            'batches' => OpeningBalanceBatch::query()->with('branch')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('saldo_awal.create');

        return view('admin.saldo-awal.create', [
            'branches' => Branch::query()->where('is_active', true)->get(),
        ]);
    }

    public function store(StoreOpeningBalanceBatchRequest $request): RedirectResponse
    {
        $batch = OpeningBalanceBatch::query()->create($request->validated());

        return redirect()->route('admin.saldo-awal.show', $batch)
            ->with('status', 'Batch migrasi dibuat. Import data per sub-modul di bawah.');
    }

    public function show(OpeningBalanceBatch $batch): View
    {
        $this->authorize('saldo_awal.read');

        return view('admin.saldo-awal.show', [
            'batch' => $batch->load(['savings', 'loans.installments', 'coaLines.account']),
            'report' => $this->reconciliationService->reconcile($batch),
        ]);
    }

    public function import(ImportOpeningBalanceRequest $request, OpeningBalanceBatch $batch, string $subModule): RedirectResponse
    {
        if (! in_array($subModule, self::SUB_MODULES, true) || ! $batch->isDraft()) {
            abort(404);
        }

        $path = $request->file('file')->getRealPath();
        $mode = $request->validated('mode');

        $result = match ($subModule) {
            'savings' => $this->importService->validateSavings($batch, $path),
            'loans' => $this->importService->validateLoans($batch, $path),
            'installments' => $this->importService->validateInstallments($batch, $path),
            'coa' => $this->importService->validateCoa($batch, $path),
        };

        if ($mode === 'all_or_nothing' && $result->hasErrors()) {
            return redirect()->route('admin.saldo-awal.show', $batch)
                ->with('import_errors', $result->errors)
                ->with('error', "Import {$subModule} dibatalkan — {$this->countLabel($result)} baris bermasalah, tidak ada yang disimpan (mode All-or-nothing).");
        }

        $committed = DB::transaction(fn () => match ($subModule) {
            'savings' => $this->importService->commitSavings($batch, $result->validRows),
            'loans' => $this->importService->commitLoans($batch, $result->validRows),
            'installments' => $this->importService->commitInstallments($batch, $result->validRows),
            'coa' => $this->importService->commitCoa($batch, $result->validRows),
        });

        $message = "Import {$subModule}: {$committed} baris berhasil disimpan";
        $message .= $result->hasErrors() ? ', '.count($result->errors).' baris gagal (lihat rincian).' : '.';

        return redirect()->route('admin.saldo-awal.show', $batch)
            ->with('import_errors', $result->errors)
            ->with('status', $message);
    }

    public function lock(OpeningBalanceBatch $batch): RedirectResponse
    {
        $this->authorize('saldo_awal.lock');

        try {
            $this->lockService->lock($batch, request()->user());
        } catch (OpeningBalanceLockException $exception) {
            return redirect()->route('admin.saldo-awal.show', $batch)->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.saldo-awal.show', $batch)
            ->with('status', 'Migrasi terkunci — jurnal pembukaan telah dibuat.');
    }

    private function countLabel($result): int
    {
        return count($result->errors);
    }
}
