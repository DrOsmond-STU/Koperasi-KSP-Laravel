<?php

namespace Tests\Feature\BusinessUnit;

use App\Models\Branch;
use App\Models\BusinessUnit;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md AUTH-14 and RPT-05 (laba/rugi per unit usaha
 * terpisah) for Task 2.4.
 */
class BusinessUnitControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function manajer(): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    public function test_creating_unit_without_coa_mapping_is_rejected(): void
    {
        $user = $this->manajer();

        $response = $this->actingAs($user)->post(route('admin.unit-usaha.store'), [
            'code' => 'UNIT-001',
            'name' => 'Unit Fotokopi',
        ]);

        $response->assertSessionHasErrors(['coa_revenue_account_id', 'coa_expense_account_id']);
        $this->assertDatabaseCount('business_units', 0);
    }

    public function test_each_unit_profit_and_loss_is_reported_separately(): void
    {
        $user = $this->manajer();
        $branch = Branch::factory()->create();

        $unitA = BusinessUnit::factory()->create(['name' => 'Unit Persewaan Aula']);
        $unitB = BusinessUnit::factory()->create(['name' => 'Unit Fotokopi']);

        $this->actingAs($user)->post(route('admin.unit-usaha.transaksi'), [
            'business_unit_id' => $unitA->id,
            'branch_id' => $branch->id,
            'type' => 'pendapatan',
            'amount' => 1000000,
        ]);
        $this->actingAs($user)->post(route('admin.unit-usaha.transaksi'), [
            'business_unit_id' => $unitB->id,
            'branch_id' => $branch->id,
            'type' => 'pendapatan',
            'amount' => 200000,
        ]);
        $this->actingAs($user)->post(route('admin.unit-usaha.transaksi'), [
            'business_unit_id' => $unitB->id,
            'branch_id' => $branch->id,
            'type' => 'beban',
            'amount' => 50000,
        ]);

        $response = $this->actingAs($user)->get(route('admin.unit-usaha.index'));

        $response->assertOk();
        // Unit A: 1,000,000 profit; Unit B: 200,000 - 50,000 = 150,000 profit — reported independently.
        $response->assertSee('1.000.000');
        $response->assertSee('150.000');
    }

    public function test_role_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');

        $this->actingAs($user)
            ->get(route('admin.unit-usaha.index'))
            ->assertForbidden();
    }
}
