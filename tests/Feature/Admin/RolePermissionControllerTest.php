<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Editor Matriks Role & Permission — memungkinkan Admin/Superadmin
 * menyesuaikan izin tiap role sesuai kebijakan internal koperasi, tanpa
 * perlu mengubah kode (RolePermissionSeeder hanya menyeed default sekali).
 */
class RolePermissionControllerTest extends TestCase
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

    public function test_role_without_role_manage_permission_cannot_access_index(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)->get(route('admin.role-permission.index'))->assertForbidden();
    }

    public function test_admin_sistem_can_update_a_roles_permissions(): void
    {
        $admin = $this->adminSistem();
        $teller = Role::query()->where('name', 'teller')->firstOrFail();
        $this->assertTrue($teller->hasPermissionTo('jurnal.read'));

        $newPermissions = $teller->permissions->pluck('name')->reject(fn ($name) => $name === 'jurnal.read')->values()->all();

        $response = $this->actingAs($admin)->put(route('admin.role-permission.update', $teller), [
            'permissions' => $newPermissions,
        ]);

        $response->assertRedirect(route('admin.role-permission.index'));
        $this->assertFalse($teller->fresh()->hasPermissionTo('jurnal.read'));
        $this->assertTrue($teller->fresh()->hasPermissionTo('simpanan.create'));
    }

    public function test_permission_update_is_audit_logged(): void
    {
        $admin = $this->adminSistem();
        $teller = Role::query()->where('name', 'teller')->firstOrFail();

        $newPermissions = $teller->permissions->pluck('name')->reject(fn ($name) => $name === 'jurnal.read')->values()->all();

        $this->actingAs($admin)->put(route('admin.role-permission.update', $teller), [
            'permissions' => $newPermissions,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Role::class,
            'auditable_id' => $teller->id,
            'action' => 'permission_change',
        ]);
    }

    public function test_updating_with_no_actual_change_does_not_write_an_audit_log(): void
    {
        $admin = $this->adminSistem();
        $teller = Role::query()->where('name', 'teller')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.role-permission.update', $teller), [
            'permissions' => $teller->permissions->pluck('name')->all(),
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'auditable_type' => Role::class,
            'auditable_id' => $teller->id,
            'action' => 'permission_change',
        ]);
    }

    public function test_user_manage_and_role_manage_cannot_be_removed_from_admin_sistem(): void
    {
        $admin = $this->adminSistem();
        $adminSistemRole = Role::query()->where('name', 'admin_sistem')->firstOrFail();

        $response = $this->actingAs($admin)->put(route('admin.role-permission.update', $adminSistemRole), [
            'permissions' => ['branding.manage'],
        ]);

        $response->assertRedirect(route('admin.role-permission.index'));
        $adminSistemRole->refresh();
        $this->assertTrue($adminSistemRole->hasPermissionTo('user.manage'));
        $this->assertTrue($adminSistemRole->hasPermissionTo('role.manage'));
        $this->assertTrue($adminSistemRole->hasPermissionTo('branding.manage'));
    }

    public function test_removing_a_permission_from_a_role_immediately_revokes_access_for_its_users(): void
    {
        $admin = $this->adminSistem();
        $teller = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $teller->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $teller->id, 'scope_type' => 'all']);

        $this->assertTrue($teller->can('simpanan.delete'));

        $tellerRole = Role::query()->where('name', 'teller')->firstOrFail();
        $newPermissions = $tellerRole->permissions->pluck('name')->reject(fn ($name) => $name === 'simpanan.delete')->values()->all();

        $this->actingAs($admin)->put(route('admin.role-permission.update', $tellerRole), [
            'permissions' => $newPermissions,
        ]);

        $this->assertFalse($teller->fresh()->can('simpanan.delete'));
    }
}
