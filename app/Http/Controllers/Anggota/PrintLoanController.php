<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cetak mandiri data pinjaman/angsuran sendiri dari Portal Anggota.
 */
class PrintLoanController extends Controller
{
    use GeneratesPrintPdf;

    public function index(Request $request): Response
    {
        $member = $request->user()->member->load('loans.loanProduct');

        $pdf = $this->renderPrintPdf('prints.loans.list', [
            'members' => collect([$member]),
            'filterDescription' => 'Anggota: '.$member->name,
            'generatedAt' => now(),
        ]);

        return $pdf->download('pinjaman-saya-'.now()->format('Ymd-His').'.pdf');
    }

    public function schedule(Request $request, Loan $loan): Response
    {
        abort_unless($loan->member_id === $request->user()->member->id, 403);

        $pdf = $this->renderPrintPdf('prints.loans.schedule', [
            'loan' => $loan->load('loanProduct', 'member', 'schedules'),
            'repayments' => $loan->repayments()->latest()->get(),
            'generatedAt' => now(),
        ]);

        return $pdf->download('angsuran-saya-'.$loan->loan_number.'.pdf');
    }
}
