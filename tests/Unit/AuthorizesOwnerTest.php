<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "User tidak bisa mengubah/menghapus data user lain" — hanya pembuat
 * transaksi sendiri, atau admin_sistem/manajer, boleh membatalkan.
 */
class AuthorizesOwnerTest extends TestCase
{
    use RefreshDatabase;

    private function transaction(User $creator): SavingsTransaction
    {
        $branch = Branch::factory()->create();
        $account = SavingsAccount::factory()->create(['branch_id' => $branch->id]);
        $entry = JournalEntry::query()->create([
            'branch_id' => $branch->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Uji AuthorizesOwner',
            'created_by' => $creator->id,
        ]);

        return SavingsTransaction::query()->create([
            'branch_id' => $branch->id,
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'amount' => 10000,
            'balance_after' => 10000,
            'journal_entry_id' => $entry->id,
            'created_by' => $creator->id,
        ]);
    }

    public function test_creator_can_cancel_own_transaction(): void
    {
        $creator = User::factory()->create();
        $tx = $this->transaction($creator);

        $this->assertTrue($tx->canBeCancelledBy($creator));
    }

    public function test_unrelated_user_without_admin_or_manajer_role_cannot_cancel(): void
    {
        $creator = User::factory()->create();
        $other = User::factory()->create();
        $tx = $this->transaction($creator);

        $this->assertFalse($tx->canBeCancelledBy($other));
    }

    public function test_admin_sistem_can_cancel_any_transaction(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $creator = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin_sistem');
        $tx = $this->transaction($creator);

        $this->assertTrue($tx->canBeCancelledBy($admin));
    }

    public function test_manajer_can_cancel_any_transaction(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $creator = User::factory()->create();
        $manajer = User::factory()->create();
        $manajer->assignRole('manajer');
        $tx = $this->transaction($creator);

        $this->assertTrue($tx->canBeCancelledBy($manajer));
    }
}
