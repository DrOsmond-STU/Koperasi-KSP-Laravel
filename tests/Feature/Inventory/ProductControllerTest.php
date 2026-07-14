<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md AUTH-14: Master Barang tidak bisa aktif tanpa
 * mapping COA Persediaan/HPP/Pendapatan Penjualan yang valid.
 */
class ProductControllerTest extends TestCase
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

        $response = $this->actingAs($user)->post(route('admin.master.products.store'), [
            'code' => 'BRG-001',
            'name' => 'Beras 5kg',
            'unit' => 'karung',
        ]);

        $response->assertSessionHasErrors([
            'coa_inventory_account_id',
            'coa_cogs_account_id',
            'coa_sales_revenue_account_id',
        ]);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_creating_product_with_non_postable_account_is_rejected(): void
    {
        $user = $this->manajer();

        $headerAccount = ChartOfAccount::factory()->nonPostable()->create();
        $postableAccount = ChartOfAccount::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.master.products.store'), [
            'code' => 'BRG-002',
            'name' => 'Beras 5kg',
            'unit' => 'karung',
            'coa_inventory_account_id' => $headerAccount->id,
            'coa_cogs_account_id' => $postableAccount->id,
            'coa_sales_revenue_account_id' => $postableAccount->id,
        ]);

        $response->assertSessionHasErrors(['coa_inventory_account_id']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_creating_product_with_valid_postable_accounts_succeeds(): void
    {
        $user = $this->manajer();

        $inventory = ChartOfAccount::factory()->create();
        $cogs = ChartOfAccount::factory()->create();
        $revenue = ChartOfAccount::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.master.products.store'), [
            'code' => 'BRG-003',
            'name' => 'Beras 5kg',
            'unit' => 'karung',
            'selling_price' => 65000,
            'coa_inventory_account_id' => $inventory->id,
            'coa_cogs_account_id' => $cogs->id,
            'coa_sales_revenue_account_id' => $revenue->id,
        ]);

        $response->assertRedirect(route('admin.master.products.index'));
        $this->assertDatabaseHas('products', ['code' => 'BRG-003', 'is_active' => 1]);
    }

    public function test_role_without_permission_cannot_create_product(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');

        $account = ChartOfAccount::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.master.products.store'), [
            'code' => 'BRG-004',
            'name' => 'Beras 5kg',
            'unit' => 'karung',
            'coa_inventory_account_id' => $account->id,
            'coa_cogs_account_id' => $account->id,
            'coa_sales_revenue_account_id' => $account->id,
        ]);

        $response->assertForbidden();
    }
}
