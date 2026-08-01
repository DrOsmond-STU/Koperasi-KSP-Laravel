<?php

namespace App\Services\Loans;

use App\Exceptions\Loans\LoanRepaymentException;
use App\Exceptions\Payment\PaymentGatewayException;
use App\Models\Loan;
use App\Models\LoanRepaymentRequest;
use App\Services\Payment\XenditGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Portal Anggota's "Bayar Angsuran Mandiri" — gateway wiring over
 * LoanRepaymentService (the allocation engine). Structured identically to
 * SavingsDepositGatewayService, with one extra case: if the outstanding
 * amount shrank between invoice creation and the webhook arriving (e.g. a
 * different repayment landed in between), LoanRepaymentService would now
 * reject this as an overpayment — rather than mis-posting the ledger, the
 * request is flagged `perlu_rekonsiliasi_manual` for staff review.
 */
class LoanRepaymentGatewayService
{
    public function __construct(
        private readonly XenditGateway $gateway,
        private readonly LoanRepaymentService $repaymentService,
    ) {}

    public function request(Loan $loan, float $amount, int $requestedBy): LoanRepaymentRequest
    {
        $plan = $this->repaymentService->previewAllocation($loan, $amount);

        if ($plan['remaining_unallocated'] > 0) {
            throw LoanRepaymentException::overpayment(
                number_format($amount, 2, '.', ''),
                number_format($plan['outstanding_before'], 2, '.', ''),
            );
        }

        $repaymentRequest = LoanRepaymentRequest::query()->create([
            'branch_id' => $loan->branch_id,
            'loan_id' => $loan->id,
            'member_id' => $loan->member_id,
            'amount' => $amount,
            'status' => 'menunggu_pembayaran',
            'external_id' => 'ANGSURAN-'.Str::uuid(),
            'requested_by' => $requestedBy,
        ]);

        try {
            $invoice = $this->gateway->createInvoice(
                externalId: $repaymentRequest->external_id,
                amount: $amount,
                description: "Angsuran pinjaman {$loan->loan_number}",
                payerEmail: $repaymentRequest->requestedBy->email,
                successRedirectUrl: route('portal.payment.finished'),
            );
        } catch (PaymentGatewayException $exception) {
            $repaymentRequest->update(['status' => 'gagal']);
            throw $exception;
        }

        $repaymentRequest->update([
            'xendit_invoice_id' => $invoice['id'],
            'xendit_invoice_url' => $invoice['invoice_url'],
        ]);

        return $repaymentRequest->fresh();
    }

    public function handleWebhookPaid(string $externalId, float $amountPaid): void
    {
        DB::transaction(function () use ($externalId, $amountPaid) {
            $repaymentRequest = LoanRepaymentRequest::query()
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();

            if ($repaymentRequest === null || ! $repaymentRequest->isPending()) {
                return;
            }

            if (bccomp((string) $repaymentRequest->amount, (string) $amountPaid, 2) !== 0) {
                Log::warning('Xendit webhook (angsuran): nominal dibayar tidak cocok dengan nominal diajukan.', [
                    'external_id' => $externalId,
                    'requested' => (string) $repaymentRequest->amount,
                    'paid' => $amountPaid,
                ]);

                return;
            }

            try {
                $repayment = $this->repaymentService->recordPayment(
                    $repaymentRequest->loan,
                    (float) $repaymentRequest->amount,
                    $repaymentRequest->requested_by,
                    "Angsuran mandiri via Xendit #{$repaymentRequest->id}",
                    idempotencyKey: $externalId,
                );
            } catch (LoanRepaymentException $exception) {
                Log::warning('Xendit webhook (angsuran): perlu rekonsiliasi manual.', [
                    'external_id' => $externalId,
                    'reason' => $exception->getMessage(),
                ]);

                $repaymentRequest->update(['status' => 'perlu_rekonsiliasi_manual']);

                return;
            }

            $repaymentRequest->update([
                'status' => 'dibayar',
                'paid_at' => now(),
                'loan_repayment_id' => $repayment->id,
            ]);
        });
    }
}
