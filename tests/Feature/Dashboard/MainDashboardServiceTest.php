<?php

namespace Tests\Feature\Dashboard;

use App\Models\Branch;
use App\Models\Loan;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Accounting\JournalEngine;
use App\Services\Dashboard\MainDashboardService;
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
            'description' => 'Pendapatan bunga uji',
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
}
