<?php

namespace App\Services\Savings;

use App\Exceptions\Payment\PaymentGatewayException;
use App\Models\SavingsAccount;
use App\Models\SavingsDepositRequest;
use App\Services\Payment\XenditGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Portal Anggota's "Setor Simpanan Mandiri" — self-service deposit via a
 * Xendit-hosted Invoice checkout. request() is deliberately NOT wrapped in
 * DB::transaction() (the row is inserted first, then the outbound HTTP call
 * happens, so a slow/failing gateway call never holds a DB lock open).
 * handleWebhookPaid() delegates crediting to SavingsService::deposit() —
 * the only writer of savings_transactions/savings_accounts.balance.
 */
class SavingsDepositGatewayService
{
    public function __construct(
        private readonly XenditGateway $gateway,
        private readonly SavingsService $savingsService,
    ) {}

    public function request(SavingsAccount $account, float $amount, int $requestedBy): SavingsDepositRequest
    {
        $depositRequest = SavingsDepositRequest::query()->create([
            'branch_id' => $account->branch_id,
            'savings_account_id' => $account->id,
            'member_id' => $account->member_id,
            'amount' => $amount,
            'status' => 'menunggu_pembayaran',
            'external_id' => 'SETOR-'.Str::uuid(),
            'requested_by' => $requestedBy,
        ]);

        try {
            $invoice = $this->gateway->createInvoice(
                externalId: $depositRequest->external_id,
                amount: $amount,
                description: "Setoran simpanan {$account->account_number}",
                payerEmail: $depositRequest->requestedBy->email,
                successRedirectUrl: route('portal.payment.finished'),
            );
        } catch (PaymentGatewayException $exception) {
            $depositRequest->update(['status' => 'gagal']);
            throw $exception;
        }

        $depositRequest->update([
            'xendit_invoice_id' => $invoice['id'],
            'xendit_invoice_url' => $invoice['invoice_url'],
        ]);

        return $depositRequest->fresh();
    }

    /**
     * Row-locked + idempotent: a duplicate webhook delivery for an
     * already-`dibayar` request is a silent no-op, never a double credit.
     */
    public function handleWebhookPaid(string $externalId, float $amountPaid): void
    {
        DB::transaction(function () use ($externalId, $amountPaid) {
            $depositRequest = SavingsDepositRequest::query()
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();

            if ($depositRequest === null || ! $depositRequest->isPending()) {
                return;
            }

            if (bccomp((string) $depositRequest->amount, (string) $amountPaid, 2) !== 0) {
                Log::warning('Xendit webhook (setoran): nominal dibayar tidak cocok dengan nominal diajukan.', [
                    'external_id' => $externalId,
                    'requested' => (string) $depositRequest->amount,
                    'paid' => $amountPaid,
                ]);

                return;
            }

            $transaction = $this->savingsService->deposit(
                $depositRequest->savingsAccount,
                (float) $depositRequest->amount,
                $depositRequest->requested_by,
                "Setoran mandiri via Xendit #{$depositRequest->id}",
                idempotencyKey: $externalId,
            );

            $depositRequest->update([
                'status' => 'dibayar',
                'paid_at' => now(),
                'savings_transaction_id' => $transaction->id,
            ]);
        });
    }
}
