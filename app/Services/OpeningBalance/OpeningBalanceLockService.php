<?php

namespace App\Services\OpeningBalance;

use App\Exceptions\OpeningBalance\OpeningBalanceLockException;
use App\Models\Loan;
use App\Models\OpeningBalanceBatch;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Accounting\JournalEngine;
use App\Services\Inventory\StockLedgerEngine;
use Illuminate\Support\Facades\DB;

/**
 * Locks an opening balance batch (PRD §9.2): validates full balance one
 * more time, then in a single DB transaction (1) posts the opening journal
 * via JournalEngine, (2) materializes real SavingsAccount / Loan /
 * LoanSchedule / stock_ledger rows so the migrated data behaves like any
 * other record in the system from this point on, and (3) marks the batch
 * `locked` — irreversible (RUNBOOK §5.6: corrections afterwards go through a
 * journal adjustment, never by re-opening the batch). UPF is the one
 * sub-module that stays reconciliation-only (no materialize step) — it has
 * no ongoing balance for other modules to read, unlike stock's running
 * qty/avg-cost.
 */
class OpeningBalanceLockService
{
    public function __construct(
        private readonly JournalEngine $journalEngine,
        private readonly OpeningBalanceReconciliationService $reconciliation,
        private readonly StockLedgerEngine $stockLedgerEngine,
    ) {}

    public function lock(OpeningBalanceBatch $batch, User $user): OpeningBalanceBatch
    {
        if (! $batch->isDraft()) {
            throw OpeningBalanceLockException::alreadyLocked();
        }

        $report = $this->reconciliation->reconcile($batch);

        if (! $report->isFullyBalanced()) {
            throw OpeningBalanceLockException::notBalanced();
        }

        return DB::transaction(function () use ($batch, $user, $report) {
            $this->postOpeningJournal($batch, $user);
            $this->materializeSavingsAccounts($batch);
            $this->materializeLoans($batch, $user);
            $this->materializeStock($batch, $user);

            // AUD-05: siapa approve, hasil rekonsiliasi saat lock, batch id
            // (batch id is the AuditLog row's auditable_id, recorded
            // automatically by the Auditable trait on this update()).
            $batch->update([
                'status' => 'locked',
                'locked_by' => $user->id,
                'locked_at' => now(),
                'reconciliation_snapshot' => [
                    'coa_debit_total' => $report->coaDebitTotal,
                    'coa_credit_total' => $report->coaCreditTotal,
                    'savings_discrepancies' => $report->savingsDiscrepancies,
                    'loans_discrepancies' => $report->loansDiscrepancies,
                    'upf_discrepancies' => $report->upfDiscrepancies,
                    'stock_discrepancies' => $report->stockDiscrepancies,
                ],
            ]);

            return $batch->fresh();
        });
    }

    private function postOpeningJournal(OpeningBalanceBatch $batch, User $user): void
    {
        $lines = $batch->coaLines->map(fn ($line) => [
            'chart_of_account_id' => $line->chart_of_account_id,
            'debit' => $line->position === 'debit' ? (float) $line->amount : 0,
            'credit' => $line->position === 'kredit' ? (float) $line->amount : 0,
        ])->all();

        $this->journalEngine->post([
            'branch_id' => $batch->branch_id,
            'entry_date' => $batch->cutoff_date->toDateString(),
            'description' => "Jurnal pembukaan migrasi saldo awal (batch #{$batch->id})",
            'created_by' => $user->id,
            'source' => $batch,
            'lines' => $lines,
        ]);
    }

    private function materializeSavingsAccounts(OpeningBalanceBatch $batch): void
    {
        foreach ($batch->savings as $row) {
            SavingsAccount::query()->create([
                'branch_id' => $batch->branch_id,
                'member_id' => $row->member_id,
                'savings_product_id' => $row->savings_product_id,
                'account_number' => $row->account_number ?? $this->generateSavingsAccountNumber($row),
                'balance' => $row->balance,
                'status' => 'aktif',
                'opened_at' => $batch->cutoff_date->toDateString(),
            ]);
        }
    }

    private function materializeLoans(OpeningBalanceBatch $batch, User $user): void
    {
        foreach ($batch->loans as $row) {
            $rate = $row->loanProduct->rateAt($batch->cutoff_date);

            $loan = Loan::query()->create([
                'branch_id' => $batch->branch_id,
                'member_id' => $row->member_id,
                'loan_product_id' => $row->loan_product_id,
                'loan_number' => $row->external_loan_number ?? ('MIGRASI-'.$row->id),
                'principal_amount' => $row->original_principal,
                'tenor_days' => $row->tenor_days,
                // Pinjaman migrasi selalu bulanan (sistem lama, sebelum
                // tenor_unit ada) — lihat migrasi add_tenor_unit_to_loan_products_and_loans.
                'tenor_unit' => 'bulan',
                'interest_rate_percentage' => $rate?->rate_percentage ?? 0,
                'required_approval_count' => 0,
                'status' => 'dicairkan',
                'collectibility' => $row->collectibility,
                'created_by' => $user->id,
                'submitted_at' => $row->disbursement_date->toDateString(),
                'disbursed_at' => $row->disbursement_date->toDateString(),
            ]);

            foreach ($row->installments as $installment) {
                $loan->schedules()->create([
                    'installment_number' => $installment->installment_number,
                    'due_date' => $installment->due_date,
                    'principal_amount' => $installment->principal_amount,
                    'interest_amount' => $installment->interest_amount,
                    'total_amount' => round((float) $installment->principal_amount + (float) $installment->interest_amount, 2),
                    'status' => $installment->status,
                ]);
            }
        }
    }

    private function materializeStock(OpeningBalanceBatch $batch, User $user): void
    {
        foreach ($batch->stock as $row) {
            $this->stockLedgerEngine->receive(
                $row->product,
                $batch->branch_id,
                (string) $row->qty,
                (string) $row->unit_cost,
                'saldo_awal',
                $user->id,
                $batch,
                $batch->cutoff_date,
            );
        }
    }

    private function generateSavingsAccountNumber($row): string
    {
        return 'MIGRASI-SA-'.$row->id;
    }
}
