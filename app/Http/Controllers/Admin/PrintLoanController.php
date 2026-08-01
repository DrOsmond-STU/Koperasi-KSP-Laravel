<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\Member;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cetakan Pinjaman Anggota — semua anggota atau satu anggota (?member_id=),
 * gate pinjaman.print.
 */
class PrintLoanController extends Controller
{
    use GeneratesPrintPdf;

    public function index(Request $request): Response
    {
        $this->authorize('pinjaman.print');

        $memberId = $request->integer('member_id') ?: null;
        $branchId = $request->integer('branch_id') ?: null;

        $allowedBranchIds = $request->user()->allowedBranchIds();
        if ($branchId !== null && $allowedBranchIds !== null && ! in_array($branchId, $allowedBranchIds, true)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        $members = Member::query()
            ->with(['loans.loanProduct'])
            ->when($memberId, fn ($query) => $query->where('id', $memberId))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        $filterDescription = collect([
            $memberId ? 'Anggota: '.Member::query()->find($memberId)?->name : null,
            $branchId ? 'Cabang: '.Branch::query()->find($branchId)?->name : null,
        ])->filter()->implode(' — ') ?: 'Semua Anggota';

        $pdf = $this->renderPrintPdf('prints.loans.list', [
            'members' => $members,
            'filterDescription' => $filterDescription,
            'generatedAt' => now(),
        ]);

        return $pdf->download('cetakan-pinjaman-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * Jadwal angsuran + histori pembayaran (LoanRepayment) untuk satu pinjaman.
     */
    public function schedule(Request $request, Loan $loan): Response
    {
        $this->authorize('pinjaman.print');

        $pdf = $this->renderPrintPdf('prints.loans.schedule', [
            'loan' => $loan->load('loanProduct', 'member', 'schedules'),
            'repayments' => $loan->repayments()->latest()->get(),
            'generatedAt' => now(),
        ]);

        return $pdf->download('cetakan-angsuran-'.$loan->loan_number.'.pdf');
    }
}
