<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\Product;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Inventory\StockLedgerEngine;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_without_permission_cannot_create_sale(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_upf');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $branch = Branch::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->post(route('staf.pos.store'), [
            'branch_id' => $branch->id,
            'payment_method' => 'tunai',
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ]);

        $response->assertForbidden();
    }

    public function test_teller_can_complete_a_cash_sale(): void
    {
        $this->seed(RolePermissionSeeder::class);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $branch = Branch::factory()->create();
        $product = Product::factory()->create([
            'coa_inventory_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_cogs_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_sales_revenue_account_id' => ChartOfAccount::factory()->create()->id,
        ]);
        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id);

        $response = $this->actingAs($user)->post(route('staf.pos.store'), [
            'branch_id' => $branch->id,
            'payment_method' => 'tunai',
            'items' => [
                ['product_id' => $product->id, 'qty' => 2],
                ['product_id' => '', 'qty' => ''],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('pos_sales', 1);
    }

    public function test_teller_can_complete_a_hutang_sale(): void
    {
        $this->seed(RolePermissionSeeder::class);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $branch = Branch::factory()->create();
        $product = Product::factory()->create([
            'coa_inventory_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_cogs_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_sales_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'selling_price' => 2000,
        ]);
        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id);

        $member = Member::factory()->create(['branch_id' => $branch->id]);
        $loanProduct = LoanProduct::factory()->create(['min_plafon' => 0]);

        $response = $this->actingAs($user)->post(route('staf.pos.store'), [
            'branch_id' => $branch->id,
            'payment_method' => 'hutang',
            'member_id' => $member->id,
            'loan_product_id' => $loanProduct->id,
            'tenor_days' => 6,
            'items' => [
                ['product_id' => $product->id, 'qty' => 3],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseHas('loans', ['status' => 'dicairkan', 'member_id' => $member->id]);
    }

    public function test_hutang_sale_missing_member_or_loan_product_fails_validation(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $branch = Branch::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->post(route('staf.pos.store'), [
            'branch_id' => $branch->id,
            'payment_method' => 'hutang',
            'items' => [
                ['product_id' => $product->id, 'qty' => 1],
            ],
        ]);

        $response->assertSessionHasErrors(['member_id', 'loan_product_id', 'tenor_days']);
    }

    public function test_hutang_sale_with_out_of_range_plafon_redirects_with_friendly_error(): void
    {
        $this->seed(RolePermissionSeeder::class);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $branch = Branch::factory()->create();
        $product = Product::factory()->create([
            'coa_inventory_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_cogs_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_sales_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'selling_price' => 2000,
        ]);
        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id);

        $member = Member::factory()->create(['branch_id' => $branch->id]);
        $loanProduct = LoanProduct::factory()->create(['min_plafon' => 1000000, 'max_plafon' => 5000000]);

        $response = $this->actingAs($user)->post(route('staf.pos.store'), [
            'branch_id' => $branch->id,
            'payment_method' => 'hutang',
            'member_id' => $member->id,
            'loan_product_id' => $loanProduct->id,
            'tenor_days' => 6,
            'items' => [
                ['product_id' => $product->id, 'qty' => 3],
            ],
        ]);

        $response->assertRedirect(route('staf.pos.create'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('pos_sales', 0);
    }
}
