<?php

namespace Tests\Feature\Loans;

use App\Exceptions\Loans\LoanRepaymentException;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanSchedule;
use App\Models\User;
use App\Services\Loans\LoanRepaymentService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The loan-repayment engine that never existed before this feature —
 * loan_schedules.paid_amount was always 0. Covers allocation (oldest
 * installment first, interest before principal within a row), the journal
 * split (Dr Kas / Cr Piutang Pinjaman / Cr Pendapatan Jasa, mirroring
 * disburse()'s account resolution via LoanProduct), and the Loan.status
 * transition to `lunas` that nothing else in the codebase ever sets.
 */
class LoanRepaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    /** Three installments of Rp 1.000.000 pokok + Rp 100.000 jasa each. */
    private function disbursedLoanWithThreeInstallments(): Loan
    {
        $product = LoanProduct::factory()->create();
        $loan = Loan::factory()->create(['loan_product_id' => $product->id, 'status' => 'dicairkan', 'principal_amount' => 3000000]);

        for ($i = 1; $i <= 3; $i++) {
            LoanSchedule::query()->create([
                'loan_id' => $loan->id,
                'installment_number' => $i,
                'due_date' => now()->addMonths($i),
                'principal_amount' => 1000000,
                'interest_amount' => 100000,
                'total_amount' => 1100000,
                'paid_amount' => 0,
                'status' => 'belum_bayar',
            ]);
        }

        return $loan->fresh();
    }

    public function test_preview_allocation_reports_outstanding_and_a_dry_run_split(): void
    {
        $loan = $this->disbursedLoanWithThreeInstallments();

        $plan = app(LoanRepaymentService::class)->previewAllocation($loan, 1100000);

        $this->assertEquals(3300000, $plan['outstanding_before']);
        $this->assertEquals(1000000, $plan['total_principal']);
        $this->assertEquals(100000, $plan['total_interest']);
        $this->assertEquals(0, $plan['remaining_unallocated']);
        $this->assertCount(1, $plan['allocations']);
        $this->assertDatabaseHas('loan_schedules', ['loan_id' => $loan->id, 'installment_number' => 1, 'paid_amount' => 0]);
    }

    public function test_full_installment_payment_marks_that_schedule_lunas(): void
    {
        $loan = $this->disbursedLoanWithThreeInstallments();
        $payer = User::factory()->create();

        $repayment = app(LoanRepaymentService::class)->recordPayment($loan, 1100000, $payer->id);

        $this->assertEquals(1000000, (float) $repayment->principal_portion);
        $this->assertEquals(100000, (float) $repayment->interest_portion);
        $this->assertDatabaseHas('loan_schedules', [
            'loan_id' => $loan->id, 'installment_number' => 1,
            'status' => 'lunas', 'paid_principal_amount' => 1000000, 'paid_interest_amount' => 100000,
        ]);
        $this->assertEquals('dicairkan', $loan->fresh()->status);
    }

    public function test_partial_payment_allocates_interest_before_principal(): void
    {
        $loan = $this->disbursedLoanWithThreeInstallments();
        $payer = User::factory()->create();

        app(LoanRepaymentService::class)->recordPayment($loan, 50000, $payer->id);

        $this->assertDatabaseHas('loan_schedules', [
            'loan_id' => $loan->id, 'installment_number' => 1,
            'status' => 'sebagian', 'paid_principal_amount' => 0, 'paid_interest_amount' => 50000,
        ]);
    }

    public function test_lump_sum_spans_multiple_installments(): void
    {
        $loan = $this->disbursedLoanWithThreeInstallments();
        $payer = User::factory()->create();

        // 1.5x one installment: covers #1 fully, then #2's interest fully + part of its principal.
        app(LoanRepaymentService::class)->recordPayment($loan, 1650000, $payer->id);

        $this->assertDatabaseHas('loan_schedules', ['loan_id' => $loan->id, 'installment_number' => 1, 'status' => 'lunas']);
        $this->assertDatabaseHas('loan_schedules', [
            'loan_id' => $loan->id, 'installment_number' => 2,
            'status' => 'sebagian', 'paid_interest_amount' => 100000, 'paid_principal_amount' => 450000,
        ]);
        $this->assertDatabaseHas('loan_schedules', ['loan_id' => $loan->id, 'installment_number' => 3, 'status' => 'belum_bayar']);
    }

    public function test_paying_off_every_installment_marks_the_loan_lunas(): void
    {
        $loan = $this->disbursedLoanWithThreeInstallments();
        $payer = User::factory()->create();

        app(LoanRepaymentService::class)->recordPayment($loan, 3300000, $payer->id);

        $this->assertEquals('lunas', $loan->fresh()->status);
        $this->assertDatabaseMissing('loan_schedules', ['loan_id' => $loan->id, 'status' => 'belum_bayar']);
        $this->assertDatabaseMissing('loan_schedules', ['loan_id' => $loan->id, 'status' => 'sebagian']);
    }

    public function test_journal_entry_splits_principal_and_interest_to_the_products_own_accounts(): void
    {
        $loan = $this->disbursedLoanWithThreeInstallments();
        $payer = User::factory()->create();

        $repayment = app(LoanRepaymentService::class)->recordPayment($loan, 1100000, $payer->id);

        $entry = $repayment->journalEntry;
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(1100000, (float) $entry->lines->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(1100000, (float) $entry->lines->sum('credit'), 0.01);

        $product = $loan->loanProduct;
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->chart_of_account_id === $product->coa_receivable_account_id && (float) $line->credit === 1000000.0));
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->chart_of_account_id === $product->coa_interest_income_account_id && (float) $line->credit === 100000.0));
    }

    public function test_overpayment_beyond_total_outstanding_is_rejected(): void
    {
        $loan = $this->disbursedLoanWithThreeInstallments();
        $payer = User::factory()->create();

        $this->expectException(LoanRepaymentException::class);

        try {
            app(LoanRepaymentService::class)->recordPayment($loan, 4000000, $payer->id);
        } finally {
            $this->assertDatabaseHas('loan_schedules', ['loan_id' => $loan->id, 'installment_number' => 1, 'paid_amount' => 0]);
            $this->assertDatabaseCount('journal_entries', 0);
        }
    }

    public function test_repayment_against_a_loan_that_is_not_disbursed_is_rejected(): void
    {
        $loan = Loan::factory()->create(['status' => 'diajukan']);
        $payer = User::factory()->create();

        $this->expectException(LoanRepaymentException::class);

        app(LoanRepaymentService::class)->recordPayment($loan, 100000, $payer->id);
    }
}
