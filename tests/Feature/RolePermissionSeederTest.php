<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RolePermissionSeeder only seeds a role's DEFAULT permission set the
 * first time it's created — after that, Admin/Superadmin owns the
 * assignment via Admin\RolePermissionController, and re-running this
 * seeder (migrate:fresh --seed, deploys) must never revert it.
 */
class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseeding_does_not_revert_a_manually_customized_role(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $teller = Role::query()->where('name', 'teller')->firstOrFail();
        $this->assertTrue($teller->hasPermissionTo('jurnal.read'));

        $teller->syncPermissions(['simpanan.read']);

        $this->seed(RolePermissionSeeder::class);

        $teller->refresh();
        $this->assertTrue($teller->hasPermissionTo('simpanan.read'));
        $this->assertFalse($teller->hasPermissionTo('jurnal.read'));
        $this->assertCount(1, $teller->permissions);
    }

    public function test_permission_rows_from_module_actions_always_exist_after_reseeding(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $teller = Role::query()->where('name', 'teller')->firstOrFail();
        $teller->syncPermissions(['simpanan.read']);

        $this->seed(RolePermissionSeeder::class);

        $this->assertTrue(Permission::query()->where('name', 'jurnal.read')->exists());
    }

    public function test_a_role_created_for_the_first_time_still_gets_its_default_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = Role::query()->where('name', 'admin_sistem')->firstOrFail();
        $this->assertTrue($admin->hasPermissionTo('user.manage'));
        $this->assertTrue($admin->hasPermissionTo('role.manage'));
    }
}
