<?php

namespace Tests\Feature\Savings;

use App\Models\ChartOfAccount;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md AUTH-14: master data dinamis tidak bisa aktif tanpa
 * mapping COA valid — ditolak saat simpan, bukan baru gagal saat transaksi.
 */
class SavingsProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        return $user;
    }

    public function test_creating_product_without_coa_mapping_is_rejected(): void
    {
        $user = $this->manajer();

        $response = $this->actingAs($user)->post(route('admin.master.savings-products.store'), [
            'code' => 'SIM-001',
            'name' => 'Simpanan Sukarela',
            'category' => 'sukarela',
            'interest_method' => 'flat',
            'initial_rate_percentage' => 2.0,
            // coa_liability_account_id and coa_interest_expense_account_id intentionally omitted
        ]);

        $response->assertSessionHasErrors(['coa_liability_account_id', 'coa_interest_expense_account_id']);
        $this->assertDatabaseCount('savings_products', 0);
    }

    public function test_creating_product_with_non_postable_account_is_rejected(): void
    {
        $user = $this->manajer();

        $headerAccount = ChartOfAccount::factory()->nonPostable()->create();
        $postableAccount = ChartOfAccount::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.master.savings-products.store'), [
            'code' => 'SIM-002',
            'name' => 'Simpanan Sukarela',
            'category' => 'sukarela',
            'interest_method' => 'flat',
            'initial_rate_percentage' => 2.0,
            'coa_liability_account_id' => $headerAccount->id,
            'coa_interest_expense_account_id' => $postableAccount->id,
        ]);

        $response->assertSessionHasErrors(['coa_liability_account_id']);
        $this->assertDatabaseCount('savings_products', 0);
    }

    public function test_creating_product_with_valid_postable_accounts_succeeds(): void
    {
        $user = $this->manajer();

        $liability = ChartOfAccount::factory()->create();
        $expense = ChartOfAccount::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.master.savings-products.store'), [
            'code' => 'SIM-003',
            'name' => 'Simpanan Sukarela',
            'category' => 'sukarela',
            'interest_method' => 'flat',
            'initial_rate_percentage' => 2.0,
            'coa_liability_account_id' => $liability->id,
            'coa_interest_expense_account_id' => $expense->id,
        ]);

        $response->assertRedirect(route('admin.master.savings-products.index'));
        $this->assertDatabaseHas('savings_products', ['code' => 'SIM-003', 'is_active' => 1]);
        $this->assertDatabaseHas('savings_product_rate_history', ['rate_percentage' => 2.000]);
    }

    public function test_role_without_permission_cannot_create_product(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');

        $liability = ChartOfAccount::factory()->create();
        $expense = ChartOfAccount::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.master.savings-products.store'), [
            'code' => 'SIM-004',
            'name' => 'Simpanan Sukarela',
            'category' => 'sukarela',
            'interest_method' => 'flat',
            'initial_rate_percentage' => 2.0,
            'coa_liability_account_id' => $liability->id,
            'coa_interest_expense_account_id' => $expense->id,
        ]);

        $response->assertForbidden();
    }
}
