<?php

namespace Tests\Feature\Dashboard;

use App\Models\Branch;
use App\Models\User;
use App\Services\Accounting\JournalEngine;
use App\Services\Dashboard\CashBankDashboardService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md LED-09 for the Kas/Bank dashboard.
 */
class CashBankDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_cash_balance_reflects_posted_journal_entries(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        app(JournalEngine::class)->post([
            'branch_id' => $branch->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Setoran modal awal',
            'created_by' => $user->id,
            'lines' => [
                ['account_code' => '1101', 'debit' => 1000000, 'credit' => 0],
                ['account_code' => '3120', 'debit' => 0, 'credit' => 1000000],
            ],
        ]);

        $summary = app(CashBankDashboardService::class)->summary($branch->id);

        $this->assertEquals(1000000, $summary['total_balance']);
        $this->assertEquals(1000000, $summary['today_in']);
        $this->assertEquals(0, $summary['today_out']);
        $this->assertCount(1, $summary['recent_entries']);
    }

    public function test_empty_branch_has_zero_balance_and_no_recent_entries(): void
    {
        $branch = Branch::factory()->create();

        $summary = app(CashBankDashboardService::class)->summary($branch->id);

        $this->assertEquals(0, $summary['total_balance']);
        $this->assertCount(0, $summary['recent_entries']);
    }
}
