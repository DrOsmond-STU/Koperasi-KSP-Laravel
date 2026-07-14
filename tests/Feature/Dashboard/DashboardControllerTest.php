<?php

namespace Tests\Feature\Dashboard;

use App\Models\Branch;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md AUTH-02/AUTH-04 for the dashboard's Cabang Scope
 * branch picker.
 */
class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_single_branch_user_cannot_view_another_branch_dashboard(): void
    {
        $ownBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();

        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'single', 'single_branch_id' => $ownBranch->id]);

        $this->actingAs($user)
            ->get(route('admin.dashboard.index', ['branch_id' => $otherBranch->id]))
            ->assertForbidden();
    }

    public function test_single_branch_user_defaults_to_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'single', 'single_branch_id' => $branch->id]);

        $response = $this->actingAs($user)->get(route('admin.dashboard.index'));

        $response->assertOk();
        $response->assertViewHas('selectedBranchId', $branch->id);
        $response->assertViewHas('isConsolidated', false);
    }

    public function test_all_branch_user_sees_consolidated_view_by_default(): void
    {
        Branch::factory()->count(2)->create();
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $response = $this->actingAs($user)->get(route('admin.dashboard.index'));

        $response->assertOk();
        $response->assertViewHas('isConsolidated', true);
    }

    public function test_kas_bank_dashboard_enforces_same_branch_scope(): void
    {
        $ownBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();

        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'single', 'single_branch_id' => $ownBranch->id]);

        $this->actingAs($user)
            ->get(route('admin.dashboard.kas-bank', ['branch_id' => $otherBranch->id]))
            ->assertForbidden();
    }
}
