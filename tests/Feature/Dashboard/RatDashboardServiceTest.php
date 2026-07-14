<?php

namespace Tests\Feature\Dashboard;

use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use App\Services\Accounting\JournalEngine;
use App\Services\Dashboard\RatDashboardService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md LED-09 for the RAT dashboard (Task 2.5).
 */
class RatDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_shu_only_counts_current_year_activity(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        // Current year revenue.
        app(JournalEngine::class)->post([
            'branch_id' => $branch->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Pendapatan tahun ini',
            'created_by' => $user->id,
            'lines' => [
                ['account_code' => '1101', 'debit' => 400000, 'credit' => 0],
                ['account_code' => '4101', 'debit' => 0, 'credit' => 400000],
            ],
        ]);

        // Prior year revenue — must NOT be counted in this year's SHU.
        app(JournalEngine::class)->post([
            'branch_id' => $branch->id,
            'entry_date' => now()->subYear()->toDateString(),
            'description' => 'Pendapatan tahun lalu',
            'created_by' => $user->id,
            'lines' => [
                ['account_code' => '1101', 'debit' => 9999999, 'credit' => 0],
                ['account_code' => '4101', 'debit' => 0, 'credit' => 9999999],
            ],
        ]);

        $summary = app(RatDashboardService::class)->summary($branch->id, (int) now()->year);

        $this->assertEquals(400000, $summary['pendapatan']);
        $this->assertEquals(400000, $summary['shu']);
    }

    public function test_member_growth_counts_only_members_joined_this_year(): void
    {
        $branch = Branch::factory()->create();
        Member::factory()->create(['branch_id' => $branch->id, 'joined_at' => now()->toDateString()]);
        Member::factory()->create(['branch_id' => $branch->id, 'joined_at' => now()->subYears(2)->toDateString()]);

        $summary = app(RatDashboardService::class)->summary($branch->id, (int) now()->year);

        $this->assertEquals(1, $summary['member_growth']->sum('total'));
        $this->assertEquals(2, $summary['total_members']); // total_members is all-time, not year-filtered
    }

    public function test_readiness_checklist_reflects_actual_data_state(): void
    {
        $branch = Branch::factory()->create();

        $summary = app(RatDashboardService::class)->summary($branch->id);

        $this->assertFalse($summary['readiness_checklist']['Neraca & Laba Rugi siap disusun']);
        $this->assertFalse($summary['readiness_checklist']['Data anggota tersedia']);
    }
}
