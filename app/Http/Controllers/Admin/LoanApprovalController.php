<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Loans\LoanApprovalException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelLoanDisbursementRequest;
use App\Http\Requests\DecideLoanApprovalRequest;
use App\Models\Loan;
use App\Services\Loans\LoanApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoanApprovalController extends Controller
{
    public function __construct(private readonly LoanApprovalService $approvalService) {}

    public function index(): View
    {
        $this->authorize('pinjaman.approve');

        return view('admin.pinjaman.index', [
            'pendingLoans' => Loan::query()
                ->where('status', 'diajukan')
                ->with(['member', 'loanProduct', 'approvals'])
                ->latest()
                ->get(),
            'disbursedLoans' => Loan::query()
                ->whereIn('status', ['dicairkan', 'dibatalkan'])
                ->with(['member', 'loanProduct'])
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function decide(DecideLoanApprovalRequest $request, Loan $loan): RedirectResponse
    {
        try {
            if ($request->validated('decision') === 'setuju') {
                $this->approvalService->approve($loan, $request->user(), $request->validated('notes'));
                $fresh = $loan->fresh();
                $message = $fresh->status === 'dicairkan'
                    ? "Pinjaman {$loan->loan_number} disetujui penuh dan dicairkan."
                    : "Persetujuan Anda tercatat — menunggu approval tambahan ({$fresh->approvalCount()}/{$loan->required_approval_count}).";
            } else {
                $this->approvalService->reject($loan, $request->user(), $request->validated('notes'));
                $message = "Pinjaman {$loan->loan_number} ditolak.";
            }
        } catch (LoanApprovalException $exception) {
            return redirect()->route('admin.pinjaman.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.pinjaman.index')->with('status', $message);
    }

    public function cancel(CancelLoanDisbursementRequest $request, Loan $loan): RedirectResponse
    {
        abort_unless($loan->canBeCancelledBy($request->user()), 403, 'Anda hanya bisa membatalkan pencairan pinjaman yang Anda buat sendiri.');

        try {
            $this->approvalService->cancelDisbursement($loan, $request->validated('reason'), $request->user()->id);
        } catch (LoanApprovalException $exception) {
            return redirect()->route('admin.pinjaman.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.pinjaman.index')->with('status', "Pencairan pinjaman {$loan->loan_number} berhasil dibatalkan.");
    }
}
