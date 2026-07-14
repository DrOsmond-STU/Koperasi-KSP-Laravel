<?php

namespace App\Http\Controllers\Staf;

use App\Http\Controllers\Controller;
use App\Http\Requests\TellerSavingsTransactionRequest;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Services\Savings\SavingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TellerController extends Controller
{
    public function __construct(private readonly SavingsService $savings) {}

    public function create(): View
    {
        $this->authorize('simpanan.create');

        return view('staf.teller', [
            'accounts' => SavingsAccount::query()->where('status', 'aktif')->with(['member', 'savingsProduct'])->latest()->limit(50)->get(),
            'recentTransactions' => SavingsTransaction::query()
                ->whereDate('created_at', now()->toDateString())
                ->with(['savingsAccount.member'])
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    /**
     * Shows the Journal Preview (DESIGN §Transaction Panel) before the
     * Teller confirms the transaction.
     */
    public function preview(TellerSavingsTransactionRequest $request): View
    {
        $account = SavingsAccount::query()->findOrFail($request->validated('savings_account_id'));

        return view('staf.teller-preview', [
            'account' => $account,
            'type' => $request->validated('type'),
            'amount' => (float) $request->validated('amount'),
            'description' => $request->validated('description'),
            'lines' => $this->savings->previewLines($account, $request->validated('type'), (float) $request->validated('amount')),
        ]);
    }

    public function store(TellerSavingsTransactionRequest $request): RedirectResponse
    {
        $account = SavingsAccount::query()->findOrFail($request->validated('savings_account_id'));
        $idempotencyKey = $request->input('idempotency_key') ?: (string) Str::uuid();

        $transaction = $request->validated('type') === 'setor'
            ? $this->savings->deposit($account, (float) $request->validated('amount'), $request->user()->id, $request->validated('description'), $idempotencyKey)
            : $this->savings->withdraw($account, (float) $request->validated('amount'), $request->user()->id, $request->validated('description'), $idempotencyKey);

        return redirect()
            ->route('staf.teller.create')
            ->with('status', "Transaksi {$request->validated('type')} Rp ".number_format($transaction->amount, 0, ',', '.').' berhasil disimpan.');
    }
}
