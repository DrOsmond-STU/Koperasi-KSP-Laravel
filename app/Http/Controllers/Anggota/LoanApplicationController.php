<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitMemberLoanApplicationRequest;
use App\Models\LoanProduct;
use App\Services\Loans\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengajuan Pinjaman lewat Portal Anggota — beda dari Staf\LoanApplicationController:
 * tidak ada picker "atas nama anggota siapa", member selalu diambil dari
 * akun yang login sendiri (lihat SubmitMemberLoanApplicationRequest).
 * Setelah terkirim, alur persetujuan berjenjang sama persis dengan yang
 * sudah ada (LoanApprovalService, ditangani staf).
 */
class LoanApplicationController extends Controller
{
    public function __construct(private readonly LoanService $loanService) {}

    public function create(Request $request): View
    {
        return view('portal.loan-application', [
            // Cuma produk yang sudah dikonfigurasi tenor harian — lihat
            // catatan yang sama di Staf\LoanApplicationController::create().
            'products' => LoanProduct::query()->where('is_active', true)->whereNotNull('min_tenor_days')->get(),
            'recentApplications' => $request->user()->member->loans()->latest()->limit(5)->get(),
        ]);
    }

    public function store(SubmitMemberLoanApplicationRequest $request): RedirectResponse
    {
        $member = $request->user()->member;
        $product = LoanProduct::query()->findOrFail($request->validated('loan_product_id'));

        $loan = $this->loanService->submitApplication(
            $member,
            $product,
            (float) $request->validated('principal_amount'),
            (int) $request->validated('tenor_days'),
            $member->branch_id,
            $request->user()->id,
        );

        return redirect()
            ->route('portal.loan-application.create')
            ->with('status', "Pengajuan pinjaman {$loan->loan_number} berhasil dikirim, menunggu persetujuan.");
    }
}
