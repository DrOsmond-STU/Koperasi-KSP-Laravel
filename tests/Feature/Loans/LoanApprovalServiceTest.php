<?php

namespace Tests\Feature\Loans;

use App\Exceptions\Loans\LoanApprovalException;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\User;
use App\Services\Loans\LoanApprovalService;
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
            'tenor_months' => 10,
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
}
