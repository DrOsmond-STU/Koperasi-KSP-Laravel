<?php

namespace App\Http\Controllers\Staf;

use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelSavingsTransactionRequest;
use App\Http\Requests\DecideSavingsWithdrawalRequest;
use App\Http\Requests\TellerSavingsTransactionRequest;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\SavingsWithdrawalRequest;
use App\Services\Savings\SavingsService;
use App\Services\Savings\SavingsWithdrawalRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TellerController extends Controller
{
    use GeneratesPrintPdf;

    public function __construct(
        private readonly SavingsService $savings,
        private readonly SavingsWithdrawalRequestService $withdrawalRequests,
    ) {}

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
            'pendingWithdrawals' => SavingsWithdrawalRequest::query()
                ->where('status', 'menunggu')
                ->with(['savingsAccount', 'member'])
                ->latest()
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

    public function cancel(CancelSavingsTransactionRequest $request, SavingsTransaction $transaction): RedirectResponse
    {
        abort_unless($transaction->canBeCancelledBy($request->user()), 403, 'Anda hanya bisa membatalkan transaksi yang Anda buat sendiri.');

        $this->savings->reverseTransaction($transaction, $request->validated('reason'), $request->user()->id);

        return redirect()
            ->route('staf.teller.create')
            ->with('status', "Transaksi {$transaction->id} berhasil dibatalkan.");
    }

    /**
     * Bukti Kas Keluar (tarik) / Kas Masuk (setor) — setiap transaksi teller
     * bisa dicetak ulang kapan saja, bukan hanya sekali saat dibuat.
     */
    public function printReceipt(SavingsTransaction $transaction): Response
    {
        $this->authorize('simpanan.print');

        $pdf = $this->renderPrintPdf('prints.savings.receipt', [
            'transaction' => $transaction->load('savingsAccount.member', 'createdBy'),
            'documentGroup' => $transaction->type === 'tarik' ? 'kas_keluar' : 'kas_masuk',
        ]);

        return $pdf->download('bukti-'.$transaction->type.'-'.$transaction->id.'.pdf');
    }

    public function decideWithdrawal(DecideSavingsWithdrawalRequest $request, SavingsWithdrawalRequest $withdrawalRequest): RedirectResponse
    {
        if ($request->validated('decision') === 'setuju') {
            $this->withdrawalRequests->approve($withdrawalRequest, $request->user()->id);
            $message = "Pengajuan penarikan #{$withdrawalRequest->id} disetujui dan diproses.";
        } else {
            $this->withdrawalRequests->reject($withdrawalRequest, $request->user()->id, $request->validated('notes'));
            $message = "Pengajuan penarikan #{$withdrawalRequest->id} ditolak.";
        }

        return redirect()
            ->route('staf.teller.create')
            ->with('status', $message);
    }
}
