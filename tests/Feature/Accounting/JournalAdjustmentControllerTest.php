<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Accounting\JournalEngine;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JournalAdjustmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function postOriginalEntry(User $user): JournalEntry
    {
        $branch = Branch::factory()->create();
        $accountA = ChartOfAccount::factory()->create();
        $accountB = ChartOfAccount::factory()->create();

        return app(JournalEngine::class)->post([
            'branch_id' => $branch->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Entri uji',
            'created_by' => $user->id,
            'lines' => [
                ['chart_of_account_id' => $accountA->id, 'debit' => 50000, 'credit' => 0],
                ['chart_of_account_id' => $accountB->id, 'debit' => 0, 'credit' => 50000],
            ],
        ]);
    }

    public function test_role_without_permission_cannot_post_adjustment(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now(), 'password' => Hash::make('secret123')]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);
        $entry = $this->postOriginalEntry($user);

        $response = $this->actingAs($user)->post(route('admin.jurnal-penyesuaian.store', $entry), [
            'reason' => 'Koreksi',
            'password' => 'secret123',
        ]);

        $response->assertForbidden();
    }

    public function test_wrong_password_is_rejected_with_flash_error_and_no_reversal_posted(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now(), 'password' => Hash::make('secret123')]);
        $user->assignRole('bendahara');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);
        $entry = $this->postOriginalEntry($user);

        $response = $this->actingAs($user)->post(route('admin.jurnal-penyesuaian.store', $entry), [
            'reason' => 'Koreksi',
            'password' => 'password-salah',
        ]);

        $response->assertRedirect(route('admin.jurnal-penyesuaian.create'));
        $this->assertDatabaseMissing('journal_entries', ['reversal_of_entry_id' => $entry->id]);
    }

    public function test_bendahara_can_post_a_reversal_with_correct_password(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now(), 'password' => Hash::make('secret123')]);
        $user->assignRole('bendahara');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);
        $entry = $this->postOriginalEntry($user);

        $response = $this->actingAs($user)->post(route('admin.jurnal-penyesuaian.store', $entry), [
            'reason' => 'Koreksi salah akun',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('admin.jurnal-penyesuaian.create'));
        $this->assertDatabaseHas('journal_entries', ['reversal_of_entry_id' => $entry->id]);
    }
}
