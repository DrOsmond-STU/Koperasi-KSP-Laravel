<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\AdjustmentException;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Accounting\AdjustmentService;
use App\Services\Accounting\JournalEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md LED-06 (koreksi jurnal -> jurnal balik terbentuk,
 * entri asal tetap ada) and the re-auth requirement from PRD §5.2.
 */
class AdjustmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reverse_creates_a_reversal_entry_and_keeps_the_original(): void
    {
        $branch = Branch::factory()->create();
        $accountA = ChartOfAccount::factory()->create();
        $accountB = ChartOfAccount::factory()->create();
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $original = app(JournalEngine::class)->post([
            'branch_id' => $branch->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Entri keliru',
            'created_by' => $user->id,
            'lines' => [
                ['chart_of_account_id' => $accountA->id, 'debit' => 100000, 'credit' => 0],
                ['chart_of_account_id' => $accountB->id, 'debit' => 0, 'credit' => 100000],
            ],
        ]);

        $reversal = app(AdjustmentService::class)->reverse($original, 'Salah akun', $user, 'secret123');

        $this->assertNotEquals($original->id, $reversal->id);
        $this->assertEquals($original->id, $reversal->reversal_of_entry_id);
        $this->assertDatabaseHas('journal_entries', ['id' => $original->id]);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $original->id, 'chart_of_account_id' => $accountA->id, 'debit' => '100000.00']);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $reversal->id, 'chart_of_account_id' => $accountA->id, 'credit' => '100000.00']);
    }

    public function test_reverse_rejects_wrong_password(): void
    {
        $branch = Branch::factory()->create();
        $accountA = ChartOfAccount::factory()->create();
        $accountB = ChartOfAccount::factory()->create();
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $original = app(JournalEngine::class)->post([
            'branch_id' => $branch->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Entri keliru',
            'created_by' => $user->id,
            'lines' => [
                ['chart_of_account_id' => $accountA->id, 'debit' => 100000, 'credit' => 0],
                ['chart_of_account_id' => $accountB->id, 'debit' => 0, 'credit' => 100000],
            ],
        ]);

        $this->expectException(AdjustmentException::class);
        app(AdjustmentService::class)->reverse($original, 'Salah akun', $user, 'password-salah');
    }
}
