<?php

namespace App\Services\Savings;

use App\Exceptions\Savings\InsufficientBalanceException;
use App\Exceptions\Savings\WithdrawalRequestException;
use App\Models\SavingsAccount;
use App\Models\SavingsWithdrawalRequest;
use Illuminate\Support\Facades\DB;

/**
 * Portal Anggota's "Pengajuan Penarikan Simpanan" — a request/approval
 * queue, not self-service money movement (that needs a payment gateway,
 * out of scope for now). Approving delegates to SavingsService::withdraw(),
 * the only writer of savings_transactions/savings_accounts.balance.
 */
class SavingsWithdrawalRequestService
{
    public function __construct(private readonly SavingsService $savingsService) {}

    public function request(SavingsAccount $account, float $amount, int $requestedBy): SavingsWithdrawalRequest
    {
        if (bccomp((string) $account->balance, (string) $amount, 2) < 0) {
            throw new InsufficientBalanceException($account->account_number, (string) $account->balance, (string) $amount);
        }

        return SavingsWithdrawalRequest::query()->create([
            'branch_id' => $account->branch_id,
            'savings_account_id' => $account->id,
            'member_id' => $account->member_id,
            'amount' => $amount,
            'status' => 'menunggu',
            'requested_by' => $requestedBy,
        ]);
    }

    /**
     * Re-validates the balance at approval time — it may have moved since
     * the request was submitted (other deposits/withdrawals in between).
     */
    public function approve(SavingsWithdrawalRequest $withdrawalRequest, int $reviewedBy): SavingsWithdrawalRequest
    {
        if (! $withdrawalRequest->isPending()) {
            throw WithdrawalRequestException::notPending($withdrawalRequest->status);
        }

        return DB::transaction(function () use ($withdrawalRequest, $reviewedBy) {
            $account = $withdrawalRequest->savingsAccount;

            $transaction = $this->savingsService->withdraw(
                $account,
                (float) $withdrawalRequest->amount,
                $reviewedBy,
                "Penarikan disetujui dari pengajuan #{$withdrawalRequest->id}",
            );

            $withdrawalRequest->update([
                'status' => 'disetujui',
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'savings_transaction_id' => $transaction->id,
            ]);

            return $withdrawalRequest->fresh();
        });
    }

    public function reject(SavingsWithdrawalRequest $withdrawalRequest, int $reviewedBy, string $notes): SavingsWithdrawalRequest
    {
        if (! $withdrawalRequest->isPending()) {
            throw WithdrawalRequestException::notPending($withdrawalRequest->status);
        }

        $withdrawalRequest->update([
            'status' => 'ditolak',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'decision_notes' => $notes,
        ]);

        return $withdrawalRequest->fresh();
    }
}
