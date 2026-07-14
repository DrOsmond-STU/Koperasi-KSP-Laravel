<?php

namespace Tests\Feature\Savings;

use App\Exceptions\Savings\InsufficientBalanceException;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\User;
use App\Services\Savings\SavingsService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md §2 (LED-01, LED-07) and §2.4 (histori tarif tidak
 * retroaktif) for the Savings module (Task 1.12).
 */
class SavingsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The "1101 Kas" account (used as the default cash leg) must exist.
        $this->seed(ChartOfAccountsSeeder::class);
    }

    /** LED-01: a deposit produces a balanced journal entry and updates the cached balance. */
    public function test_deposit_creates_balanced_journal_and_updates_balance(): void
    {
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $user = User::factory()->create();

        $transaction = app(SavingsService::class)->deposit($account, 150000, $user->id);

        $this->assertEquals(150000, $transaction->amount);
        $this->assertEquals(150000, $account->fresh()->balance);

        $entry = $transaction->journalEntry;
        $this->assertEquals(
            $entry->lines->sum('debit'),
            $entry->lines->sum('credit'),
        );
    }

    /** LED-07: withdrawing more than the balance is rejected and posts no journal. */
    public function test_withdraw_more_than_balance_is_rejected(): void
    {
        $account = SavingsAccount::factory()->create(['balance' => 50000]);
        $user = User::factory()->create();

        $this->expectException(InsufficientBalanceException::class);

        try {
            app(SavingsService::class)->withdraw($account, 100000, $user->id);
        } finally {
            $this->assertEquals(50000, $account->fresh()->balance);
            $this->assertDatabaseCount('savings_transactions', 0);
        }
    }

    public function test_withdraw_within_balance_succeeds(): void
    {
        $account = SavingsAccount::factory()->create(['balance' => 100000]);
        $user = User::factory()->create();

        $transaction = app(SavingsService::class)->withdraw($account, 40000, $user->id);

        $this->assertEquals(60000, $account->fresh()->balance);
        $this->assertEquals(60000, $transaction->balance_after);
    }

    /** Rate history: a future rate change doesn't retroactively affect past resolution. */
    public function test_rate_history_is_not_retroactive(): void
    {
        $product = SavingsProduct::factory()->create();

        // factory already seeds one rate (2.5%) effective a year ago.
        $pastDate = now()->subMonths(6);
        $this->assertEquals(2.5, $product->rateAt($pastDate)->rate_percentage);

        // Add a new rate effective in the future.
        $product->rateHistory()->create([
            'rate_percentage' => 4.0,
            'effective_from' => now()->addMonth()->toDateString(),
        ]);

        // Past date resolution is unaffected by the future-dated rate.
        $this->assertEquals(2.5, (float) $product->rateAt($pastDate)->rate_percentage);
        // Today still resolves to the old rate (new one isn't effective yet).
        $this->assertEquals(2.5, (float) $product->rateAt(now())->rate_percentage);
        // But resolving "in the future" picks up the new rate.
        $this->assertEquals(4.0, (float) $product->rateAt(now()->addMonths(2))->rate_percentage);
    }

    public function test_tiered_rate_resolves_by_balance_bracket(): void
    {
        $product = SavingsProduct::factory()->create(['interest_method' => 'tiered']);

        $rate = $product->rateHistory()->create([
            'rate_percentage' => 1.0,
            'effective_from' => now()->subMonth()->toDateString(),
            'tiers' => [
                ['min_balance' => 0, 'max_balance' => 999999, 'rate_percentage' => 1.0],
                ['min_balance' => 1000000, 'max_balance' => null, 'rate_percentage' => 3.0],
            ],
        ]);

        $this->assertEquals(1.0, $rate->rateForBalance(500000));
        $this->assertEquals(3.0, $rate->rateForBalance(5000000));
    }
}
