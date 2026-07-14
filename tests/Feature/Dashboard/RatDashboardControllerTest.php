<?php

namespace Tests\Feature\Dashboard;

use App\Models\Branch;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_rat_dashboard_renders_for_all_branch_user(): void
    {
        Branch::factory()->count(2)->create();
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $response = $this->actingAs($user)->get(route('admin.dashboard.rat'));

        $response->assertOk();
        $response->assertSee('Dashboard RAT');
    }

    public function test_single_branch_user_cannot_view_another_branch_rat_dashboard(): void
    {
        $ownBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'single', 'single_branch_id' => $ownBranch->id]);

        $this->actingAs($user)
            ->get(route('admin.dashboard.rat', ['branch_id' => $otherBranch->id]))
            ->assertForbidden();
    }
}
