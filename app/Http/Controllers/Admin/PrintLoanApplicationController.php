<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cetakan Pengajuan Pinjaman — data pengajuan + approval nyata yang sudah
 * ada (Loan::approvals) ditambah blok tanda tangan statis (Pengurus/Ketua
 * Koperasi) dari App\Models\DocumentSignatureSlot.
 */
class PrintLoanApplicationController extends Controller
{
    use GeneratesPrintPdf;

    public function show(Loan $loan): Response
    {
        $this->authorize('pinjaman.read');

        $pdf = $this->renderPrintPdf('prints.loans.application', [
            'loan' => $loan->load('member', 'loanProduct', 'createdBy', 'approvals.approvedBy'),
            'generatedAt' => now(),
        ]);

        return $pdf->download('pengajuan-pinjaman-'.$loan->loan_number.'.pdf');
    }
}
