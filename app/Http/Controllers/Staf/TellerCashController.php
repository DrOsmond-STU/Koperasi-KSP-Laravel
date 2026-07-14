<?php

namespace App\Http\Controllers\Staf;

use App\Http\Controllers\Controller;
use App\Http\Requests\TellerCashTransactionRequest;
use App\Models\Branch;
use App\Models\CashCategory;
use App\Models\TellerCashTransaction;
use App\Services\Cash\TellerCashService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TellerCashController extends Controller
{
    public function __construct(private readonly TellerCashService $cashService) {}

    public function create(): View
    {
        $this->authorize('kas_teller.create');

        $allowed = request()->user()->allowedBranchIds();

        return view('staf.kas', [
            'categories' => CashCategory::query()->where('is_active', true)->get(),
            'branches' => $allowed === null
                ? Branch::query()->where('is_active', true)->get()
                : Branch::query()->where('is_active', true)->whereIn('id', $allowed)->get(),
            'recentTransactions' => TellerCashTransaction::query()
                ->whereDate('created_at', now()->toDateString())
                ->with(['category', 'createdBy'])
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function preview(TellerCashTransactionRequest $request): View
    {
        $category = CashCategory::query()->findOrFail($request->validated('cash_category_id'));

        return view('staf.kas-preview', [
            'category' => $category,
            'branchId' => $request->validated('branch_id'),
            'amount' => (float) $request->validated('amount'),
            'description' => $request->validated('description'),
            'lines' => $this->cashService->previewLines($category, (float) $request->validated('amount')),
        ]);
    }

    public function store(TellerCashTransactionRequest $request): RedirectResponse
    {
        $category = CashCategory::query()->findOrFail($request->validated('cash_category_id'));
        $idempotencyKey = $request->input('idempotency_key') ?: (string) Str::uuid();

        $transaction = $this->cashService->record(
            $category,
            (float) $request->validated('amount'),
            (int) $request->validated('branch_id'),
            $request->user()->id,
            $request->validated('description'),
            idempotencyKey: $idempotencyKey,
        );

        return redirect()
            ->route('staf.kas.create')
            ->with('status', "Kas {$category->type} Rp ".number_format($transaction->amount, 0, ',', '.').' berhasil disimpan.');
    }
}
