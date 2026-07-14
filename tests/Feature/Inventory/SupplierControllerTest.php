<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md AUTH-14: Master Supplier tidak bisa aktif tanpa
 * mapping COA Hutang Usaha yang valid.
 */
class SupplierControllerTest extends TestCase
{
    use RefreshDatabase;

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        return $user;
    }

    public function test_creating_supplier_without_coa_mapping_is_rejected(): void
    {
        $user = $this->manajer();

        $response = $this->actingAs($user)->post(route('admin.master.suppliers.store'), [
            'code' => 'SUP-001',
            'name' => 'CV Sumber Makmur',
            'payment_term' => 'tunai',
        ]);

        $response->assertSessionHasErrors(['coa_payable_account_id']);
        $this->assertDatabaseCount('suppliers', 0);
    }

    public function test_creating_kredit_supplier_without_term_days_is_rejected(): void
    {
        $user = $this->manajer();
        $account = ChartOfAccount::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.master.suppliers.store'), [
            'code' => 'SUP-002',
            'name' => 'CV Sumber Makmur',
            'payment_term' => 'kredit',
            'coa_payable_account_id' => $account->id,
        ]);

        $response->assertSessionHasErrors(['payment_term_days']);
        $this->assertDatabaseCount('suppliers', 0);
    }

    public function test_creating_supplier_with_valid_postable_account_succeeds(): void
    {
        $user = $this->manajer();
        $account = ChartOfAccount::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.master.suppliers.store'), [
            'code' => 'SUP-003',
            'name' => 'CV Sumber Makmur',
            'payment_term' => 'kredit',
            'payment_term_days' => 30,
            'coa_payable_account_id' => $account->id,
        ]);

        $response->assertRedirect(route('admin.master.suppliers.index'));
        $this->assertDatabaseHas('suppliers', ['code' => 'SUP-003', 'is_active' => 1]);
    }

    public function test_role_without_permission_cannot_create_supplier(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');

        $account = ChartOfAccount::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.master.suppliers.store'), [
            'code' => 'SUP-004',
            'name' => 'CV Sumber Makmur',
            'payment_term' => 'tunai',
            'coa_payable_account_id' => $account->id,
        ]);

        $response->assertForbidden();
    }
}
