<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Models\SavingsWithdrawalRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cetakan Pengajuan Penarikan Simpanan — sejajar PrintLoanApplicationController.
 */
class PrintWithdrawalRequestController extends Controller
{
    use GeneratesPrintPdf;

    public function show(SavingsWithdrawalRequest $withdrawalRequest): Response
    {
        $this->authorize('simpanan.read');

        $pdf = $this->renderPrintPdf('prints.savings.withdrawal-request', [
            'withdrawalRequest' => $withdrawalRequest->load('member', 'savingsAccount', 'requestedBy', 'reviewedBy'),
            'generatedAt' => now(),
        ]);

        return $pdf->download('pengajuan-penarikan-'.$withdrawalRequest->id.'.pdf');
    }
}
