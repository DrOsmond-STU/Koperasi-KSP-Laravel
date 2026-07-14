<?php

namespace Tests\Feature\Cash;

use App\Models\Branch;
use App\Models\CashCategory;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Cash\TellerCashService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md LED-01 for Kas Teller (Task 2.1).
 */
class TellerCashServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_kas_masuk_debits_cash_and_credits_category_account(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $category = CashCategory::factory()->create();

        $transaction = app(TellerCashService::class)->record($category, 250000, $branch->id, $user->id);

        $entry = $transaction->journalEntry;
        $this->assertEquals($entry->lines->sum('debit'), $entry->lines->sum('credit'));
        $this->assertEquals(250000, (float) $entry->lines->firstWhere('debit', '>', 0)->debit);
    }

    public function test_kas_keluar_debits_category_and_credits_cash_account(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $category = CashCategory::factory()->keluar()->create();

        $transaction = app(TellerCashService::class)->record($category, 100000, $branch->id, $user->id);

        $entry = $transaction->journalEntry;
        $this->assertEquals($entry->lines->sum('debit'), $entry->lines->sum('credit'));

        $kasLine = $entry->lines->firstWhere('chart_of_account_id', ChartOfAccount::where('code', '1101')->first()->id);
        $this->assertEquals(100000, (float) $kasLine->credit);
    }
}
