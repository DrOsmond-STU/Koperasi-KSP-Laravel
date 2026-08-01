<?php

namespace App\Http\Controllers\Anggota;

use App\Exceptions\Loans\LoanRepaymentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RequestLoanRepaymentRequest;
use App\Models\Loan;
use App\Models\LoanRepaymentRequest;
use App\Services\Loans\LoanRepaymentGatewayService;
use App\Services\Loans\LoanRepaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bayar Angsuran Mandiri lewat Portal Anggota — sama seperti Setor
 * Simpanan Mandiri, tidak ada antrean persetujuan staf: begitu Xendit
 * konfirmasi lunas lewat webhook, angsuran langsung tercatat otomatis.
 */
class LoanRepaymentController extends Controller
{
    public function __construct(
        private readonly LoanRepaymentGatewayService $gateway,
        private readonly LoanRepaymentService $repaymentService,
    ) {}

    public function create(Request $request, Loan $loan): View
    {
        $member = $request->user()->member;

        abort_unless($loan->member_id === $member->id, 403);

        // amount=0 walks no rows but still computes outstanding_before up
        // front — single source of truth shared with recordPayment()'s guard.
        $outstanding = $this->repaymentService->previewAllocation($loan, 0)['outstanding_before'];

        return view('portal.loans.repayment', [
            'loan' => $loan->load('loanProduct'),
            'outstanding' => $outstanding,
            'gatewayConfigured' => filled(config('services.xendit.secret_key')),
            'history' => LoanRepaymentRequest::query()
                ->where('loan_id', $loan->id)
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(RequestLoanRepaymentRequest $request, Loan $loan): RedirectResponse
    {
        $member = $request->user()->member;

        abort_unless($loan->member_id === $member->id, 403);
        abort_if(blank(config('services.xendit.secret_key')), 403, 'Fitur pembayaran mandiri belum aktif.');

        try {
            $repaymentRequest = $this->gateway->request($loan, (float) $request->validated('amount'), $request->user()->id);
        } catch (LoanRepaymentException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()])->withInput();
        }

        return redirect($repaymentRequest->xendit_invoice_url);
    }
}
