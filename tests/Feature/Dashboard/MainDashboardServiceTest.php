<?php

namespace Tests\Feature\Dashboard;

use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Accounting\JournalEngine;
use App\Services\Dashboard\MainDashboardService;
use App\Services\Savings\SavingsService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md LED-09: dashboard numbers must match the underlying
 * ledger/records exactly (single source of truth), for both per-branch and
 * consolidated views.
 */
class MainDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_totals_match_underlying_records_for_a_single_branch(): void
    {
        $branch = Branch::factory()->create();
        $member = Member::factory()->create(['branch_id' => $branch->id]);

        $account = SavingsAccount::factory()->create([
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'balance' => 750000,
        ]);

        Loan::factory()->create([
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'principal_amount' => 5000000,
            'status' => 'dicairkan',
            'collectibility' => 'lancar',
        ]);
        Loan::factory()->create([
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'principal_amount' => 2000000,
            'status' => 'dicairkan',
            'collectibility' => 'macet',
        ]);

        $summary = app(MainDashboardService::class)->summary($branch->id);

        $this->assertEquals(1, $summary['total_members']);
        $this->assertEquals(750000, $summary['total_savings']);
        $this->assertEquals(7000000, $summary['total_loan_outstanding']);
        // NPL: 2,000,000 macet / 7,000,000 total = 28.57%
        $this->assertEquals(28.57, $summary['npl_ratio']);
    }

    public function test_other_branch_data_is_excluded_from_single_branch_summary(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        SavingsAccount::factory()->create(['branch_id' => $branchA->id, 'balance' => 100000]);
        SavingsAccount::factory()->create(['branch_id' => $branchB->id, 'balance' => 999999]);

        $summary = app(MainDashboardService::class)->summary($branchA->id);

        $this->assertEquals(100000, $summary['total_savings']);
    }

    public function test_consolidated_view_sums_across_all_branches(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        SavingsAccount::factory()->create(['branch_id' => $branchA->id, 'balance' => 100000]);
        SavingsAccount::factory()->create(['branch_id' => $branchB->id, 'balance' => 200000]);

        $summary = app(MainDashboardService::class)->summary(null);

        $this->assertEquals(300000, $summary['total_savings']);
    }

    public function test_shu_running_matches_pendapatan_minus_beban_from_journal(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        app(JournalEngine::class)->post([
            'branch_id' => $branch->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Pendapatan jasa uji',
            'created_by' => $user->id,
            'lines' => [
                ['account_code' => '1101', 'debit' => 500000, 'credit' => 0],
                ['account_code' => '4101', 'debit' => 0, 'credit' => 500000],
            ],
        ]);

        app(JournalEngine::class)->post([
            'branch_id' => $branch->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Beban gaji uji',
            'created_by' => $user->id,
            'lines' => [
                ['account_code' => '5201', 'debit' => 120000, 'credit' => 0],
                ['account_code' => '1101', 'debit' => 0, 'credit' => 120000],
            ],
        ]);

        $summary = app(MainDashboardService::class)->summary($branch->id);

        $this->assertEquals(380000, $summary['shu_running']); // 500,000 - 120,000
    }

    public function test_due_installments_lists_unpaid_schedules_ordered_by_due_date(): void
    {
        $branch = Branch::factory()->create();
        $member = Member::factory()->create(['branch_id' => $branch->id]);
        $loan = Loan::factory()->create([
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'status' => 'dicairkan',
        ]);

        $paidSchedule = LoanSchedule::query()->create([
            'loan_id' => $loan->id,
            'installment_number' => 1,
            'due_date' => now()->subMonth(),
            'principal_amount' => 400000,
            'interest_amount' => 40000,
            'total_amount' => 440000,
            'paid_amount' => 440000,
            'status' => 'lunas',
        ]);
        $laterSchedule = LoanSchedule::query()->create([
            'loan_id' => $loan->id,
            'installment_number' => 3,
            'due_date' => now()->addDays(2),
            'principal_amount' => 400000,
            'interest_amount' => 40000,
            'total_amount' => 440000,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);
        $soonestSchedule = LoanSchedule::query()->create([
            'loan_id' => $loan->id,
            'installment_number' => 2,
            'due_date' => now()->addDay(),
            'principal_amount' => 400000,
            'interest_amount' => 40000,
            'total_amount' => 440000,
            'paid_amount' => 200000,
            'status' => 'sebagian',
        ]);

        $summary = app(MainDashboardService::class)->summary($branch->id);

        $this->assertCount(2, $summary['due_installments']);
        $this->assertEquals($soonestSchedule->id, $summary['due_installments'][0]->id);
        $this->assertEquals($laterSchedule->id, $summary['due_installments'][1]->id);
        $this->assertFalse($summary['due_installments']->contains('id', $paidSchedule->id));
    }

    public function test_due_installments_excludes_other_branch_loans(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $loanA = Loan::factory()->create(['branch_id' => $branchA->id, 'status' => 'dicairkan']);
        $loanB = Loan::factory()->create(['branch_id' => $branchB->id, 'status' => 'dicairkan']);

        LoanSchedule::query()->create([
            'loan_id' => $loanA->id, 'installment_number' => 1, 'due_date' => now(),
            'principal_amount' => 100000, 'interest_amount' => 0, 'total_amount' => 100000,
            'paid_amount' => 0, 'status' => 'belum_bayar',
        ]);
        LoanSchedule::query()->create([
            'loan_id' => $loanB->id, 'installment_number' => 1, 'due_date' => now(),
            'principal_amount' => 200000, 'interest_amount' => 0, 'total_amount' => 200000,
            'paid_amount' => 0, 'status' => 'belum_bayar',
        ]);

        $summary = app(MainDashboardService::class)->summary($branchA->id);

        $this->assertCount(1, $summary['due_installments']);
        $this->assertEquals($loanA->id, $summary['due_installments'][0]->loan_id);
    }

    public function test_recent_loan_activity_lists_all_statuses_ordered_by_most_recently_updated(): void
    {
        $branch = Branch::factory()->create();

        $rejected = Loan::factory()->create(['branch_id' => $branch->id, 'status' => 'ditolak', 'updated_at' => now()->subDays(2)]);
        $disbursed = Loan::factory()->create(['branch_id' => $branch->id, 'status' => 'dicairkan', 'updated_at' => now()->subHour()]);
        $submitted = Loan::factory()->create(['branch_id' => $branch->id, 'status' => 'diajukan', 'updated_at' => now()]);

        $summary = app(MainDashboardService::class)->summary($branch->id);

        $this->assertCount(3, $summary['recent_loan_activity']);
        $this->assertEquals($submitted->id, $summary['recent_loan_activity'][0]->id);
        $this->assertEquals($disbursed->id, $summary['recent_loan_activity'][1]->id);
        $this->assertEquals($rejected->id, $summary['recent_loan_activity'][2]->id);
    }

    public function test_recent_loan_activity_excludes_other_branch_loans(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        Loan::factory()->create(['branch_id' => $branchA->id, 'status' => 'diajukan']);
        Loan::factory()->create(['branch_id' => $branchB->id, 'status' => 'diajukan']);

        $summary = app(MainDashboardService::class)->summary($branchA->id);

        $this->assertCount(1, $summary['recent_loan_activity']);
    }

    public function test_daily_activity_covers_fourteen_days_and_aggregates_todays_figures(): void
    {
        $branch = Branch::factory()->create();
        $member = Member::factory()->create(['branch_id' => $branch->id]);
        $user = User::factory()->create();

        $account = SavingsAccount::factory()->create(['branch_id' => $branch->id, 'member_id' => $member->id, 'balance' => 0]);
        app(SavingsService::class)->deposit($account, 150000, $user->id);

        $loan = Loan::factory()->create([
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'status' => 'dicairkan',
            'principal_amount' => 3000000,
            'disbursed_at' => now()->toDateString(),
        ]);
        LoanSchedule::query()->create([
            'loan_id' => $loan->id, 'installment_number' => 1, 'due_date' => now()->toDateString(),
            'principal_amount' => 250000, 'interest_amount' => 25000, 'total_amount' => 275000,
            'paid_amount' => 0, 'status' => 'belum_bayar',
        ]);

        $summary = app(MainDashboardService::class)->summary($branch->id);
        $activity = $summary['daily_activity'];

        $this->assertCount(14, $activity);
        $today = collect($activity)->last();
        $this->assertEquals(150000.0, $today['simpanan']);
        $this->assertEquals(3000000.0, $today['pinjaman']);
        $this->assertEquals(275000.0, $today['angsuran']);
        $this->assertEquals(3425000.0, $today['total']);
    }

    public function test_daily_activity_excludes_cancelled_savings_transactions(): void
    {
        $branch = Branch::factory()->create();
        $member = Member::factory()->create(['branch_id' => $branch->id]);
        $user = User::factory()->create();

        $account = SavingsAccount::factory()->create(['branch_id' => $branch->id, 'member_id' => $member->id, 'balance' => 0]);
        $transaction = app(SavingsService::class)->deposit($account, 150000, $user->id);
        app(SavingsService::class)->reverseTransaction($transaction, 'Salah input', $user->id);

        $summary = app(MainDashboardService::class)->summary($branch->id);

        $this->assertEquals(0.0, collect($summary['daily_activity'])->last()['simpanan']);
    }
}
