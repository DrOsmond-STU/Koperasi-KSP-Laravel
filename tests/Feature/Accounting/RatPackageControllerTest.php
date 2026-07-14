<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatPackageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manajer_can_download_rat_package(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $response = $this->actingAs($user)->get(route('admin.rat.paket.download', ['year' => now()->format('Y')]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_role_without_permission_cannot_download_rat_package(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_upf');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)->get(route('admin.rat.paket.download'))->assertForbidden();
    }
}
