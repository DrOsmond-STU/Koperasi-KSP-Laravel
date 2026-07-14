<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manajer_can_view_saldo_and_kartu_reports(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)->get(route('admin.persediaan.laporan.saldo'))->assertOk();
        $this->actingAs($user)->get(route('admin.persediaan.laporan.kartu'))->assertOk();
    }

    public function test_role_without_permission_cannot_view_reports(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)->get(route('admin.persediaan.laporan.saldo'))->assertForbidden();
    }
}
