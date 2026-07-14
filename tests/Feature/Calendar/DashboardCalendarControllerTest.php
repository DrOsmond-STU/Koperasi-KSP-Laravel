<?php

namespace Tests\Feature\Calendar;

use App\Models\Branch;
use App\Models\User;
use App\Models\UserBranchScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_dashboard_renders_for_all_branch_user(): void
    {
        Branch::factory()->count(2)->create();
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $response = $this->actingAs($user)->get(route('admin.dashboard.kalender', ['year' => 2026, 'month' => 8]));

        $response->assertOk();
        $response->assertSee('Dashboard Kalender');
    }
}
