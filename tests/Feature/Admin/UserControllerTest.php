<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Manajemen User & Role — user.manage sudah ada di seeder, digrant ke
 * admin_sistem, tapi belum ada UI-nya sebelum controller ini dibangun.
 */
class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminSistem(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('admin_sistem');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    public function test_role_without_user_manage_permission_cannot_access_index(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_sistem_can_create_a_new_user_with_role_and_branch_scope(): void
    {
        $admin = $this->adminSistem();
        $branch = Branch::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Teller Baru',
            'email' => 'teller.baru@koperasi.local',
            'password' => 'Vhq9wLbTn3fZ',
            'password_confirmation' => 'Vhq9wLbTn3fZ',
            'roles' => ['teller'],
            'scope_type' => 'single',
            'single_branch_id' => $branch->id,
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'teller.baru@koperasi.local')->firstOrFail();
        $this->assertTrue($created->hasRole('teller'));
        $this->assertTrue($created->is_active);
        $this->assertEquals('single', $created->branchScope->scope_type);
        $this->assertEquals($branch->id, $created->branchScope->single_branch_id);
    }

    public function test_admin_sistem_can_update_user_roles_and_it_is_audit_logged(): void
    {
        $admin = $this->adminSistem();
        $target = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $target->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $target->id, 'scope_type' => 'all']);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['manajer'],
            'scope_type' => 'all',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertTrue($target->hasRole('manajer'));
        $this->assertFalse($target->hasRole('teller'));

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => User::class,
            'auditable_id' => $target->id,
            'action' => 'role_change',
        ]);
    }

    public function test_leaving_password_blank_on_update_keeps_existing_password(): void
    {
        $admin = $this->adminSistem();
        $target = User::factory()->create([
            'password' => Hash::make('password-lama'),
            'two_factor_confirmed_at' => now(),
        ]);
        $target->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $target->id, 'scope_type' => 'all']);

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['teller'],
            'scope_type' => 'all',
        ]);

        $this->assertTrue(Hash::check('password-lama', $target->fresh()->password));
    }

    public function test_deactivated_user_cannot_reach_protected_routes(): void
    {
        $admin = $this->adminSistem();
        $target = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $target->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $target->id, 'scope_type' => 'all']);

        $this->actingAs($admin)->post(route('admin.users.deactivate', $target))
            ->assertRedirect(route('admin.users.index'));

        $this->assertFalse($target->fresh()->is_active);

        $this->actingAs($target->fresh())->get(route('home'))->assertForbidden();
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin = $this->adminSistem();

        $this->actingAs($admin)->post(route('admin.users.deactivate', $admin))
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_reactivating_a_user_restores_access(): void
    {
        $admin = $this->adminSistem();
        $target = User::factory()->create(['two_factor_confirmed_at' => now(), 'is_active' => false]);
        $target->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $target->id, 'scope_type' => 'all']);

        $this->actingAs($admin)->post(route('admin.users.reactivate', $target));

        $this->assertTrue($target->fresh()->is_active);
        $this->actingAs($target->fresh())->get(route('home'))->assertOk();
    }

    public function test_admin_sistem_can_link_a_member_when_creating_an_anggota_user(): void
    {
        $admin = $this->adminSistem();
        $member = Member::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Anggota Baru',
            'email' => 'anggota.baru@koperasi.local',
            'password' => 'Vhq9wLbTn3fZ',
            'password_confirmation' => 'Vhq9wLbTn3fZ',
            'roles' => ['anggota'],
            'member_id' => $member->id,
            'scope_type' => 'all',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'anggota.baru@koperasi.local')->firstOrFail();
        $this->assertEquals($member->id, $created->member->id);
    }

    public function test_member_link_cannot_be_stolen_from_another_user(): void
    {
        $admin = $this->adminSistem();
        $ownerUser = User::factory()->create();
        $member = Member::factory()->create(['user_id' => $ownerUser->id]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Anggota Lain',
            'email' => 'anggota.lain@koperasi.local',
            'password' => 'Vhq9wLbTn3fZ',
            'password_confirmation' => 'Vhq9wLbTn3fZ',
            'roles' => ['anggota'],
            'member_id' => $member->id,
            'scope_type' => 'all',
        ]);

        $response->assertSessionHasErrors('member_id');
        $this->assertEquals($ownerUser->id, $member->fresh()->user_id);
    }

    public function test_admin_sistem_can_relink_a_users_member_on_update(): void
    {
        $admin = $this->adminSistem();
        $target = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $target->assignRole('anggota');
        UserBranchScope::query()->create(['user_id' => $target->id, 'scope_type' => 'all']);
        $oldMember = Member::factory()->create(['user_id' => $target->id]);
        $newMember = Member::factory()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['anggota'],
            'member_id' => $newMember->id,
            'scope_type' => 'all',
        ]);

        $this->assertNull($oldMember->fresh()->user_id);
        $this->assertEquals($target->id, $newMember->fresh()->user_id);
    }
}
