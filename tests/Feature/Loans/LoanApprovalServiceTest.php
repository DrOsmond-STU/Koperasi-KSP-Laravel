<?php

namespace Tests\Feature\Loans;

use App\Exceptions\Loans\LoanApprovalException;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\User;
use App\Services\Loans\LoanApprovalService;
use App\Services\Loans\LoanService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md AUTH-09, AUTH-12, and LED-01 (disbursement journal
 * balance) for the loan approval flow (Task 1.13).
 */
class LoanApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    /** AUTH-09: the applicant cannot approve their own application. */
    public function test_creator_cannot_approve_own_loan(): void
    {
        $creator = User::factory()->create();
        $loan = Loan::factory()->create(['created_by' => $creator->id, 'required_approval_count' => 1]);

        $this->expectException(LoanApprovalException::class);

        app(LoanApprovalService::class)->approve($loan, $creator);
    }

    /** AUTH-12: a loan requiring 2 approvals is not disbursed after only 1. */
    public function test_loan_requiring_two_approvals_is_not_disbursed_after_one(): void
    {
        $creator = User::factory()->create();
        $approver = User::factory()->create();
        $loan = Loan::factory()->create(['created_by' => $creator->id, 'required_approval_count' => 2]);

        $result = app(LoanApprovalService::class)->approve($loan, $approver);

        $this->assertEquals('diajukan', $result->status);
        $this->assertNull($result->disbursed_at);
        $this->assertDatabaseCount('loan_schedules', 0);
    }

    /** AUTH-12: the same person approving twice does not count as two approvals. */
    public function test_same_approver_cannot_be_counted_twice(): void
    {
        $creator = User::factory()->create();
        $approver = User::factory()->create();
        $loan = Loan::factory()->create(['created_by' => $creator->id, 'required_approval_count' => 2]);

        app(LoanApprovalService::class)->approve($loan, $approver);

        $this->expectException(LoanApprovalException::class);
        app(LoanApprovalService::class)->approve($loan, $approver);
    }

    /** Once required_approval_count is reached, disbursement posts a balanced journal and builds the schedule. */
    public function test_loan_is_disbursed_once_fully_approved(): void
    {
        $creator = User::factory()->create();
        $approverOne = User::factory()->create();
        $approverTwo = User::factory()->create();

        $loan = Loan::factory()->create([
            'created_by' => $creator->id,
            'required_approval_count' => 2,
            'principal_amount' => 10_000_000,
            'tenor_days' => 10,
        ]);

        $service = app(LoanApprovalService::class);
        $service->approve($loan, $approverOne);
        $result = $service->approve($loan, $approverTwo);

        $this->assertEquals('dicairkan', $result->status);
        $this->assertEquals('lancar', $result->collectibility);
        $this->assertNotNull($result->disbursed_at);
        $this->assertCount(10, $result->schedules);

        $entry = $result->fresh()->loanProduct; // sanity: relation still resolvable
        $this->assertNotNull($entry);

        $journalEntry = JournalEntry::query()->where('source_type', Loan::class)->where('source_id', $loan->id)->firstOrFail();
        $this->assertEquals($journalEntry->lines->sum('debit'), $journalEntry->lines->sum('credit'));
    }

    /**
     * Regresi: pinjaman yang diajukan SEBELUM perbaikan tenor harian
     * (24 Agu 2026, tenor_days masih null) tetap dicairkan lewat jalur
     * bulanan lama — bukan diinterpretasi ulang sebagai tenor harian.
     */
    public function test_legacy_monthly_loan_still_disburses_via_the_old_monthly_schedule(): void
    {
        $creator = User::factory()->create();
        $approver = User::factory()->create();

        $loan = Loan::factory()->create([
            'created_by' => $creator->id,
            'required_approval_count' => 1,
            'principal_amount' => 12_000_000,
            'tenor_days' => null,
            'tenor_months' => 12,
        ]);
        $this->assertFalse($loan->usesDailyTenor());

        $result = app(LoanApprovalService::class)->approve($loan, $approver);

        $this->assertEquals('dicairkan', $result->status);
        $this->assertCount(12, $result->schedules);
        $this->assertTrue($result->schedules->first()->due_date->isSameDay(now()->addMonthNoOverflow()));
    }

    public function test_rejected_loan_is_never_disbursed(): void
    {
        $creator = User::factory()->create();
        $approver = User::factory()->create();
        $loan = Loan::factory()->create(['created_by' => $creator->id, 'required_approval_count' => 1]);

        $result = app(LoanApprovalService::class)->reject($loan, $approver, 'Tidak memenuhi syarat');

        $this->assertEquals('ditolak', $result->status);
        $this->assertDatabaseCount('loan_schedules', 0);
    }

    public function test_decision_on_non_pending_loan_is_rejected(): void
    {
        $creator = User::factory()->create();
        $approver = User::factory()->create();
        $loan = Loan::factory()->create(['created_by' => $creator->id, 'status' => 'ditolak']);

        $this->expectException(LoanApprovalException::class);
        app(LoanApprovalService::class)->approve($loan, $approver);
    }

    private function disburseLoan(int $createdBy): Loan
    {
        $creator = User::query()->findOrFail($createdBy);
        $approver = User::factory()->create();
        $loan = Loan::factory()->create([
            'created_by' => $creator->id,
            'required_approval_count' => 1,
            'principal_amount' => 5_000_000,
            'tenor_days' => 12,
        ]);

        return app(LoanApprovalService::class)->approve($loan, $approver);
    }

    public function test_cancelling_a_disbursed_loan_reverses_journal_and_deletes_schedule(): void
    {
        $creator = User::factory()->create();
        $loan = $this->disburseLoan($creator->id);
        $disbursementEntry = JournalEntry::query()->where('source_type', Loan::class)->where('source_id', $loan->id)->firstOrFail();

        $result = app(LoanApprovalService::class)->cancelDisbursement($loan, 'Salah input plafon', $creator->id);

        $this->assertEquals('dibatalkan', $result->status);
        $this->assertNotNull($result->cancelled_at);
        $this->assertNotNull($result->reversal_journal_entry_id);
        $this->assertDatabaseCount('loan_schedules', 0);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $result->reversal_journal_entry_id,
            'reversal_of_entry_id' => $disbursementEntry->id,
        ]);
    }

    public function test_cancelling_a_loan_with_a_paid_installment_is_rejected(): void
    {
        $creator = User::factory()->create();
        $loan = $this->disburseLoan($creator->id);
        $loan->schedules()->first()->update(['paid_amount' => 100000]);

        $this->expectException(LoanApprovalException::class);
        app(LoanApprovalService::class)->cancelDisbursement($loan, 'Coba batalkan', $creator->id);
    }

    public function test_cancelling_a_non_disbursed_loan_is_rejected(): void
    {
        $creator = User::factory()->create();
        $loan = Loan::factory()->create(['created_by' => $creator->id, 'status' => 'diajukan']);

        $this->expectException(LoanApprovalException::class);
        app(LoanApprovalService::class)->cancelDisbursement($loan, 'Coba batalkan', $creator->id);
    }

    public function test_cancelling_an_already_cancelled_loan_is_rejected(): void
    {
        $creator = User::factory()->create();
        $loan = $this->disburseLoan($creator->id);
        app(LoanApprovalService::class)->cancelDisbursement($loan, 'Pertama', $creator->id);

        $this->expectException(LoanApprovalException::class);
        app(LoanApprovalService::class)->cancelDisbursement($loan->fresh(), 'Kedua', $creator->id);
    }

    public function test_cancelling_a_pos_originated_loan_without_its_own_journal_is_rejected(): void
    {
        $product = LoanProduct::factory()->create(['min_plafon' => 0]);
        $member = Member::factory()->create();
        $user = User::factory()->create();

        $loan = app(LoanService::class)->originateInstantly($member, $product, 1_000_000, 150, $member->branch_id, $user->id);

        $this->expectException(LoanApprovalException::class);
        app(LoanApprovalService::class)->cancelDisbursement($loan, 'Coba batalkan', $user->id);
    }
}
