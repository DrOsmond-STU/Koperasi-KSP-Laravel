<?php

namespace Tests\Feature\Reporting;

use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FinancialReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manajer_can_view_financial_reports(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)->get(route('admin.laporan-keuangan.index'))->assertOk();
    }

    public function test_role_without_permission_cannot_view_financial_reports(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_upf');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)->get(route('admin.laporan-keuangan.index'))->assertForbidden();
    }

    public function test_manajer_can_queue_an_export(): void
    {
        Queue::fake();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $response = $this->actingAs($user)->post(route('admin.laporan-keuangan.export'), [
            'report_kind' => 'neraca',
            'basis' => 'sak_ep',
            'format' => 'pdf',
            'as_of_date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('admin.laporan-keuangan.exports'));
        $this->assertDatabaseCount('financial_report_exports', 1);
    }
}
