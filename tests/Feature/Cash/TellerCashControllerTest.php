<?php

namespace Tests\Feature\Cash;

use App\Models\Branch;
use App\Models\CashCategory;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md E2E-03 and Cabang Scope enforcement (AUTH-02) for
 * the Kas Teller endpoints.
 */
class TellerCashControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_teller_can_preview_then_commit_kas_masuk(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'single', 'single_branch_id' => $branch->id]);

        $category = CashCategory::factory()->create(['name' => 'Pendapatan Sewa']);

        $preview = $this->actingAs($user)->post(route('staf.kas.preview'), [
            'branch_id' => $branch->id,
            'cash_category_id' => $category->id,
            'amount' => 300000,
        ]);
        $preview->assertOk();
        $preview->assertSee('Pendapatan Sewa');

        $store = $this->actingAs($user)->post(route('staf.kas.store'), [
            'branch_id' => $branch->id,
            'cash_category_id' => $category->id,
            'amount' => 300000,
        ]);
        $store->assertRedirect(route('staf.kas.create'));

        $this->assertDatabaseHas('teller_cash_transactions', [
            'branch_id' => $branch->id,
            'cash_category_id' => $category->id,
            'amount' => 300000,
        ]);
    }

    /** AUTH-02: a Single-Branch teller cannot post a cash transaction to another branch. */
    public function test_teller_cannot_post_to_a_branch_outside_their_scope(): void
    {
        $ownBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'single', 'single_branch_id' => $ownBranch->id]);

        $category = CashCategory::factory()->create();

        $response = $this->actingAs($user)->post(route('staf.kas.store'), [
            'branch_id' => $otherBranch->id,
            'cash_category_id' => $category->id,
            'amount' => 100000,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('teller_cash_transactions', 0);
    }
}
