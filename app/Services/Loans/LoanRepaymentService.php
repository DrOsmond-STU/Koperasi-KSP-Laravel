<?php

namespace App\Services\Loans;

use App\Exceptions\Loans\LoanRepaymentException;
use App\Models\ChartOfAccount;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Services\Accounting\JournalEngine;
use Illuminate\Support\Facades\DB;

/**
 * The loan-repayment engine that never existed before (loan_schedules.
 * paid_amount was always 0). previewAllocation() walks unpaid schedules
 * oldest-first, interest-first within each row (paid_interest_amount /
 * paid_principal_amount are always kept in sync with paid_amount so a
 * lump sum spanning several partial-paid installments never has to
 * reverse-derive the split). recordPayment() persists exactly what
 * previewAllocation() computed — the two can never drift apart because
 * recordPayment() calls it internally rather than reimplementing the walk.
 */
class LoanRepaymentService
{
    private const DEFAULT_CASH_ACCOUNT_CODE = '1101';

    public function __construct(private readonly JournalEngine $journalEngine) {}

    /**
     * @return array{
     *     allocations: array<int, array{schedule_id: int, installment_number: int, allocated: float, principal_share: float, interest_share: float}>,
     *     total_principal: float,
     *     total_interest: float,
     *     total_allocated: float,
     *     outstanding_before: float,
     *     remaining_unallocated: float,
     * }
     */
    public function previewAllocation(Loan $loan, float $amount): array
    {
        $remaining = round($amount, 2);
        $allocations = [];
        $totalPrincipal = 0.0;
        $totalInterest = 0.0;

        $schedules = $loan->schedules()->where('status', '!=', 'lunas')->orderBy('installment_number')->get();
        $outstandingBefore = round((float) $schedules->sum(
            fn (LoanSchedule $s) => (float) $s->total_amount - (float) $s->paid_amount,
        ), 2);

        foreach ($schedules as $schedule) {
            if ($remaining <= 0) {
                break;
            }

            $remainingInterest = round((float) $schedule->interest_amount - (float) $schedule->paid_interest_amount, 2);
            $remainingPrincipal = round((float) $schedule->principal_amount - (float) $schedule->paid_principal_amount, 2);
            $remainingOnRow = round($remainingInterest + $remainingPrincipal, 2);

            $allocatedToRow = min($remainingOnRow, $remaining);

            if ($allocatedToRow <= 0) {
                continue;
            }

            $interestShare = min($allocatedToRow, $remainingInterest);
            $principalShare = round($allocatedToRow - $interestShare, 2);

            $allocations[] = [
                'schedule_id' => $schedule->id,
                'installment_number' => $schedule->installment_number,
                'allocated' => $allocatedToRow,
                'principal_share' => $principalShare,
                'interest_share' => $interestShare,
            ];

            $totalPrincipal = round($totalPrincipal + $principalShare, 2);
            $totalInterest = round($totalInterest + $interestShare, 2);
            $remaining = round($remaining - $allocatedToRow, 2);
        }

        return [
            'allocations' => $allocations,
            'total_principal' => $totalPrincipal,
            'total_interest' => $totalInterest,
            'total_allocated' => round($totalPrincipal + $totalInterest, 2),
            'outstanding_before' => $outstandingBefore,
            'remaining_unallocated' => $remaining,
        ];
    }

    /**
     * $date = tanggal pembayaran SEBENARNYA (mis. staf menyusulkan angsuran
     * lama yang belum tercatat) — default ke hari ini kalau tidak diisi.
     * Dipakai untuk loan_repayments.transaction_date DAN entry_date jurnal,
     * supaya keduanya selalu konsisten (mirror pola RetributionService::record()).
     */
    public function recordPayment(
        Loan $loan,
        float $amount,
        int $createdBy,
        ?string $description = null,
        ?string $idempotencyKey = null,
        ?\DateTimeInterface $date = null,
    ): LoanRepayment {
        if ($loan->status !== 'dicairkan') {
            throw LoanRepaymentException::notActive($loan->status);
        }

        $plan = $this->previewAllocation($loan, $amount);

        if ($plan['remaining_unallocated'] > 0) {
            throw LoanRepaymentException::overpayment(
                number_format($amount, 2, '.', ''),
                number_format($plan['outstanding_before'], 2, '.', ''),
            );
        }

        $entryDate = $date ?? now();

        return DB::transaction(function () use ($loan, $amount, $createdBy, $description, $idempotencyKey, $plan, $entryDate) {
            foreach ($plan['allocations'] as $allocation) {
                $schedule = LoanSchedule::query()->lockForUpdate()->findOrFail($allocation['schedule_id']);

                $newPaidPrincipal = round((float) $schedule->paid_principal_amount + $allocation['principal_share'], 2);
                $newPaidInterest = round((float) $schedule->paid_interest_amount + $allocation['interest_share'], 2);
                $newPaidAmount = round($newPaidPrincipal + $newPaidInterest, 2);

                $schedule->update([
                    'paid_principal_amount' => $newPaidPrincipal,
                    'paid_interest_amount' => $newPaidInterest,
                    'paid_amount' => $newPaidAmount,
                    'status' => $newPaidAmount >= (float) $schedule->total_amount ? 'lunas' : 'sebagian',
                ]);
            }

            $product = $loan->loanProduct;
            $lines = [
                ['chart_of_account_id' => $this->cashAccount()->id, 'debit' => $amount, 'credit' => 0],
            ];

            if ($plan['total_principal'] > 0) {
                $lines[] = ['chart_of_account_id' => $product->coa_receivable_account_id, 'debit' => 0, 'credit' => $plan['total_principal']];
            }

            if ($plan['total_interest'] > 0) {
                $lines[] = ['chart_of_account_id' => $product->coa_interest_income_account_id, 'debit' => 0, 'credit' => $plan['total_interest']];
            }

            // Created before the journal so it can be the entry's polymorphic
            // `source` (Loan already claims that slot for its disbursement
            // journal — see Loan::disbursementJournalEntry()).
            $repayment = LoanRepayment::query()->create([
                'branch_id' => $loan->branch_id,
                'loan_id' => $loan->id,
                'amount' => $amount,
                'principal_portion' => $plan['total_principal'],
                'interest_portion' => $plan['total_interest'],
                'balance_after' => round($plan['outstanding_before'] - $amount, 2),
                'transaction_date' => $entryDate->format('Y-m-d'),
                'created_by' => $createdBy,
                'description' => $description,
            ]);

            $entry = $this->journalEngine->post([
                'branch_id' => $loan->branch_id,
                'entry_date' => $entryDate->format('Y-m-d'),
                'description' => $description ?? "Pembayaran angsuran {$loan->loan_number}",
                'created_by' => $createdBy,
                'source' => $repayment,
                'idempotency_key' => $idempotencyKey,
                'lines' => $lines,
            ]);

            $repayment->update(['journal_entry_id' => $entry->id]);

            if (! $loan->schedules()->where('status', '!=', 'lunas')->exists()) {
                $loan->update(['status' => 'lunas']);
            }

            return $repayment->fresh();
        });
    }

    private function cashAccount(): ChartOfAccount
    {
        return ChartOfAccount::query()->where('code', self::DEFAULT_CASH_ACCOUNT_CODE)->firstOrFail();
    }
}
