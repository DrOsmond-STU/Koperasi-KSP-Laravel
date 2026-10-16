<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Models\LoanRepayment;
use App\Services\Loans\SisaPinjamanCalculator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bukti Kas Masuk untuk pembayaran angsuran — sejajar
 * Staf\TellerController::printReceipt() untuk SavingsTransaction.
 */
class PrintLoanRepaymentController extends Controller
{
    use GeneratesPrintPdf;

    public function __construct(private readonly SisaPinjamanCalculator $sisaPinjaman) {}

    public function show(LoanRepayment $repayment): Response
    {
        $this->authorize('pinjaman.print');

        $pdf = $this->renderPrintPdf('prints.loans.repayment-receipt', [
            'repayment' => $repayment->load('loan.member', 'createdBy'),
            'sisaPinjaman' => $this->sisaPinjaman->setelahTiapAngsuran([$repayment->loan_id])[$repayment->id] ?? null,
        ]);

        return $pdf->download('bukti-angsuran-'.$repayment->id.'.pdf');
    }
}
