<?php

namespace App\Http\Controllers\Staf;

use App\Exceptions\Loans\LoanRepaymentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StafLoanRepaymentRequest;
use App\Models\ChartOfAccount;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Services\Loans\LoanRepaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Catat Angsuran (teller) — pembayaran tunai yang diterima langsung di
 * loket, dicatat seketika lewat LoanRepaymentService::recordPayment().
 * Berbeda dari Anggota\LoanRepaymentController: itu Bayar Angsuran Mandiri
 * lewat Portal (gateway Xendit, nunggu webhook); ini tidak lewat gateway
 * sama sekali — sejajar Staf\TellerController untuk simpanan (preview lalu
 * konfirmasi, bukan langsung posting dari form pertama).
 */
class LoanRepaymentController extends Controller
{
    public function __construct(private readonly LoanRepaymentService $repayments) {}

    public function create(): View
    {
        $this->authorize('pinjaman.create');

        return view('staf.angsuran', [
            'loans' => Loan::query()
                ->where('status', 'dicairkan')
                ->with(['member', 'loanProduct'])
                ->orderBy('loan_number')
                ->get(),
            'recentRepayments' => LoanRepayment::query()
                ->whereDate('created_at', now()->toDateString())
                ->with(['loan.member'])
                ->latest()
                ->limit(20)
                ->get(),
            'preselectedLoanId' => request()->integer('loan_id') ?: null,
            // Daftar akun kas yang bisa dipilih staf (postable + "kas" di
            // nama, sama pola dengan BranchCashSettingsController::index())
            // + default dinamis (kas cabang USP) — lihat
            // LoanRepaymentService::defaultCashAccount().
            'cashAccounts' => ChartOfAccount::query()
                ->where('is_postable', true)
                ->where('name', 'like', '%kas%')
                ->orderBy('code')
                ->get(),
            'defaultCashAccountId' => $this->repayments->defaultCashAccount()?->id,
        ]);
    }

    public function preview(StafLoanRepaymentRequest $request): View|RedirectResponse
    {
        $loan = Loan::query()->with(['member', 'loanProduct'])->findOrFail($request->validated('loan_id'));
        $amount = (float) $request->validated('amount');

        try {
            $plan = $this->repayments->previewAllocation($loan, $amount);
        } catch (LoanRepaymentException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()])->withInput();
        }

        if ($plan['remaining_unallocated'] > 0) {
            return back()->withErrors([
                'amount' => "Nominal bayar melebihi total tunggakan saat ini (Rp ".number_format($plan['outstanding_before'], 0, ',', '.').').',
            ])->withInput();
        }

        return view('staf.angsuran-preview', [
            'loan' => $loan,
            'amount' => $amount,
            'description' => $request->validated('description'),
            'paidAt' => $request->validated('paid_at') ?: now()->toDateString(),
            'plan' => $plan,
            'cashAccountId' => $request->validated('cash_account_id'),
            'cashAccount' => ChartOfAccount::query()->find($request->validated('cash_account_id')),
        ]);
    }

    public function store(StafLoanRepaymentRequest $request): RedirectResponse
    {
        $loan = Loan::query()->findOrFail($request->validated('loan_id'));
        $idempotencyKey = $request->input('idempotency_key') ?: (string) Str::uuid();
        $paidAt = $request->validated('paid_at');

        try {
            $repayment = $this->repayments->recordPayment(
                $loan,
                (float) $request->validated('amount'),
                $request->user()->id,
                $request->validated('description'),
                $idempotencyKey,
                $paidAt ? Carbon::parse($paidAt) : null,
                (int) $request->validated('cash_account_id'),
            );
        } catch (LoanRepaymentException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('staf.angsuran.create')
            ->with('status', "Angsuran {$loan->loan_number} Rp ".number_format($repayment->amount, 0, ',', '.').' berhasil dicatat.');
    }
}
