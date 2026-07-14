<?php

namespace Tests\Feature\BusinessUnit;

use App\Models\Branch;
use App\Models\BusinessUnit;
use App\Models\User;
use App\Services\BusinessUnit\BusinessUnitService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md LED-01 for Unit Usaha Lain (Task 2.4).
 */
class BusinessUnitServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_pendapatan_debits_cash_and_credits_unit_revenue(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $unit = BusinessUnit::factory()->create();

        $transaction = app(BusinessUnitService::class)->record($unit, 'pendapatan', 500000, $branch->id, $user->id);

        $entry = $transaction->journalEntry;
        $this->assertEquals($entry->lines->sum('debit'), $entry->lines->sum('credit'));

        $revenueLine = $entry->lines->firstWhere('chart_of_account_id', $unit->coa_revenue_account_id);
        $this->assertEquals(500000, (float) $revenueLine->credit);
    }

    public function test_beban_debits_unit_expense_and_credits_cash(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $unit = BusinessUnit::factory()->create();

        $transaction = app(BusinessUnitService::class)->record($unit, 'beban', 150000, $branch->id, $user->id);

        $entry = $transaction->journalEntry;
        $expenseLine = $entry->lines->firstWhere('chart_of_account_id', $unit->coa_expense_account_id);
        $this->assertEquals(150000, (float) $expenseLine->debit);
    }
}
