<?php

namespace Tests\Feature\Ledger;

use App\Exceptions\Accounting\JournalPostingException;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Accounting\JournalEngine;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md §2 (LED-01, LED-02, LED-03, LED-04, LED-05, LED-06).
 */
class JournalEngineTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $user;

    private JournalEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->branch = Branch::query()->create([
            'code' => 'CBG-01',
            'name' => 'Cabang Utama',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();

        $this->engine = app(JournalEngine::class);
    }

    /** LED-01: balanced transaction produces a balanced journal entry. */
    public function test_post_creates_balanced_journal_entry(): void
    {
        $entry = $this->engine->post([
            'branch_id' => $this->branch->id,
            'entry_date' => '2026-07-11',
            'description' => 'Setoran simpanan sukarela',
            'created_by' => $this->user->id,
            'lines' => [
                ['account_code' => '1101', 'debit' => 100000, 'credit' => 0],
                ['account_code' => '2101', 'debit' => 0, 'credit' => 100000],
            ],
        ]);

        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id, 'branch_id' => $this->branch->id]);
        $this->assertCount(2, $entry->lines);
        $this->assertEquals(
            $entry->lines->sum('debit'),
            $entry->lines->sum('credit'),
        );
    }

    /** LED-02: an unbalanced attempt is rejected and nothing is persisted. */
    public function test_post_rejects_unbalanced_lines(): void
    {
        $this->expectException(JournalPostingException::class);

        try {
            $this->engine->post([
                'branch_id' => $this->branch->id,
                'entry_date' => '2026-07-11',
                'description' => 'Jurnal timpang',
                'created_by' => $this->user->id,
                'lines' => [
                    ['account_code' => '1101', 'debit' => 100000, 'credit' => 0],
                    ['account_code' => '2101', 'debit' => 0, 'credit' => 90000],
                ],
            ]);
        } finally {
            $this->assertDatabaseCount('journal_entries', 0);
            $this->assertDatabaseCount('journal_lines', 0);
        }
    }

    /** LED-03: a failure mid-transaction (unknown account) rolls back fully. */
    public function test_post_rolls_back_entirely_on_failure(): void
    {
        $this->expectException(JournalPostingException::class);

        try {
            $this->engine->post([
                'branch_id' => $this->branch->id,
                'entry_date' => '2026-07-11',
                'description' => 'Baris kedua tidak valid',
                'created_by' => $this->user->id,
                'lines' => [
                    ['account_code' => '1101', 'debit' => 100000, 'credit' => 0],
                    ['account_code' => 'TIDAK-ADA', 'debit' => 0, 'credit' => 100000],
                ],
            ]);
        } finally {
            $this->assertDatabaseCount('journal_entries', 0);
        }
    }

    /** LED-04: repeating the same idempotency_key returns the original entry, no duplicate. */
    public function test_post_is_idempotent(): void
    {
        $payload = [
            'branch_id' => $this->branch->id,
            'entry_date' => '2026-07-11',
            'description' => 'Setoran via API',
            'created_by' => $this->user->id,
            'idempotency_key' => 'req-abc-123',
            'lines' => [
                ['account_code' => '1101', 'debit' => 50000, 'credit' => 0],
                ['account_code' => '2101', 'debit' => 0, 'credit' => 50000],
            ],
        ];

        $first = $this->engine->post($payload);
        $second = $this->engine->post($payload);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    /** LED-05: posting into a closed accounting period is rejected. */
    public function test_post_rejects_closed_period(): void
    {
        AccountingPeriod::query()->create([
            'branch_id' => $this->branch->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'status' => 'closed',
        ]);

        $this->expectException(JournalPostingException::class);

        $this->engine->post([
            'branch_id' => $this->branch->id,
            'entry_date' => '2026-06-15',
            'description' => 'Posting mundur ke periode tertutup',
            'created_by' => $this->user->id,
            'lines' => [
                ['account_code' => '1101', 'debit' => 10000, 'credit' => 0],
                ['account_code' => '2101', 'debit' => 0, 'credit' => 10000],
            ],
        ]);
    }

    /** LED-06: correction is a new reversing entry; the original is untouched. */
    public function test_reverse_posts_new_entry_and_keeps_original(): void
    {
        $original = $this->engine->post([
            'branch_id' => $this->branch->id,
            'entry_date' => '2026-07-11',
            'description' => 'Setoran yang perlu dikoreksi',
            'created_by' => $this->user->id,
            'lines' => [
                ['account_code' => '1101', 'debit' => 75000, 'credit' => 0],
                ['account_code' => '2101', 'debit' => 0, 'credit' => 75000],
            ],
        ]);

        $reversal = $this->engine->reverse($original, 'Salah nominal', $this->user->id);

        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertEquals($original->id, $reversal->reversal_of_entry_id);
        $this->assertDatabaseHas('journal_entries', ['id' => $original->id]);

        $kasLine = $reversal->lines->firstWhere('chart_of_account_id', $original->lines->first()->chart_of_account_id);
        $this->assertEquals($original->lines->first()->debit, $kasLine->credit);
    }

    /** Posting to a header (non-postable) account is rejected. */
    public function test_post_rejects_non_postable_account(): void
    {
        $header = ChartOfAccount::query()->where('code', '1100')->firstOrFail();
        $this->assertFalse($header->is_postable);

        $this->expectException(JournalPostingException::class);

        $this->engine->post([
            'branch_id' => $this->branch->id,
            'entry_date' => '2026-07-11',
            'description' => 'Posting ke akun header',
            'created_by' => $this->user->id,
            'lines' => [
                ['account_code' => '1100', 'debit' => 10000, 'credit' => 0],
                ['account_code' => '2101', 'debit' => 0, 'credit' => 10000],
            ],
        ]);
    }
}
