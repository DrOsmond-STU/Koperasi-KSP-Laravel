<?php

namespace Tests\Feature\Auth;

use App\Models\Member;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Anggota-only accounts must land on Portal Anggota after login, not the
 * staff/admin dashboard — staff/admin accounts (including ones that also
 * happen to hold the anggota role) keep the existing behaviour unchanged.
 */
class RoleAwareLoginResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_anggota_only_user_is_redirected_to_the_portal(): void
    {
        $member = Member::factory()->create();
        $user = User::factory()->create(['password' => Hash::make('SomeRandomPass9')]);
        $user->assignRole('anggota');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);
        $member->update(['user_id' => $user->id]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'SomeRandomPass9',
        ]);

        $response->assertRedirect(route('portal.dashboard'));
    }

    public function test_staff_user_is_redirected_to_the_admin_dashboard(): void
    {
        $user = User::factory()->create(['password' => Hash::make('SomeRandomPass9')]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'SomeRandomPass9',
        ]);

        $response->assertRedirect('/admin/dashboard');
    }

    public function test_anggota_role_without_a_linked_member_falls_back_to_the_admin_dashboard(): void
    {
        $user = User::factory()->create(['password' => Hash::make('SomeRandomPass9')]);
        $user->assignRole('anggota');

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'SomeRandomPass9',
        ]);

        $response->assertRedirect('/admin/dashboard');
    }
}
