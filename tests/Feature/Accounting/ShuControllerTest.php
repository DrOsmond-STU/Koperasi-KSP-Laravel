<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShuControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manajer_can_view_shu_simulation(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)->get(route('admin.shu.index'))->assertOk();
    }

    public function test_role_without_permission_cannot_view_shu(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_upf');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)->get(route('admin.shu.index'))->assertForbidden();
    }

    public function test_role_without_permission_cannot_add_category(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_upf');

        $this->actingAs($user)->post(route('admin.shu.kategori.store'), [
            'name' => 'Coba',
            'percentage' => 10,
        ])->assertForbidden();
    }
}
