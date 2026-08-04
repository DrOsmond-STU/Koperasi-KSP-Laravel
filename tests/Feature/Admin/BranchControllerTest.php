<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD Cabang — sebelum ini cabang hanya bisa dibuat lewat wizard instalasi
 * atau `tinker`, sehingga koperasi tidak punya cara menambah kantor cabang
 * kedua setelah go-live.
 */
class BranchControllerTest extends TestCase
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

    public function test_admin_sistem_can_see_the_branch_list(): void
    {
        $admin = $this->adminSistem();
        $branch = Branch::factory()->create(['code' => 'PST', 'name' => 'Kantor Pusat']);

        $this->actingAs($admin)
            ->get(route('admin.master.branches.index'))
            ->assertOk()
            ->assertSee('Kantor Pusat')
            ->assertSee($branch->code);
    }

    public function test_admin_sistem_can_create_a_branch(): void
    {
        $admin = $this->adminSistem();

        $this->actingAs($admin)
            ->post(route('admin.master.branches.store'), [
                'code' => 'CAB-01',
                'name' => 'Cabang Selatan',
                'address' => 'Jl. Melati 10',
                'operational_date' => '2026-08-01',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.master.branches.index'));

        $this->assertDatabaseHas('branches', [
            'code' => 'CAB-01',
            'name' => 'Cabang Selatan',
            'is_active' => true,
        ]);
    }

    public function test_branch_code_must_be_unique(): void
    {
        $admin = $this->adminSistem();
        Branch::factory()->create(['code' => 'PST']);

        $this->actingAs($admin)
            ->post(route('admin.master.branches.store'), ['code' => 'PST', 'name' => 'Duplikat'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Branch::query()->where('code', 'PST')->count());
    }

    public function test_admin_sistem_can_update_a_branch(): void
    {
        $admin = $this->adminSistem();
        $branch = Branch::factory()->create(['code' => 'CAB-01', 'name' => 'Nama Lama']);

        $this->actingAs($admin)
            ->put(route('admin.master.branches.update', $branch), [
                'code' => 'CAB-01',
                'name' => 'Nama Baru',
                'address' => 'Alamat Baru',
            ])
            ->assertRedirect(route('admin.master.branches.index'));

        $branch->refresh();
        $this->assertSame('Nama Baru', $branch->name);
        $this->assertSame('Alamat Baru', $branch->address);
        // Checkbox tidak dikirim = nonaktif; ini yang membuat "nonaktifkan
        // cabang" bisa dilakukan dari form yang sama.
        $this->assertFalse($branch->is_active);
    }

    public function test_updating_keeps_its_own_code_without_tripping_the_unique_rule(): void
    {
        $admin = $this->adminSistem();
        $branch = Branch::factory()->create(['code' => 'PST']);

        $this->actingAs($admin)
            ->put(route('admin.master.branches.update', $branch), [
                'code' => 'PST',
                'name' => 'Kantor Pusat',
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_a_branch_cannot_become_its_own_parent(): void
    {
        $admin = $this->adminSistem();
        $branch = Branch::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.master.branches.update', $branch), [
                'code' => $branch->code,
                'name' => $branch->name,
                'parent_branch_id' => $branch->id,
            ])
            ->assertSessionHasErrors('parent_branch_id');
    }

    public function test_a_branch_cannot_take_one_of_its_descendants_as_parent(): void
    {
        $admin = $this->adminSistem();
        $pusat = Branch::factory()->create();
        $anak = Branch::factory()->create(['parent_branch_id' => $pusat->id]);
        $cucu = Branch::factory()->create(['parent_branch_id' => $anak->id]);

        $this->actingAs($admin)
            ->put(route('admin.master.branches.update', $pusat), [
                'code' => $pusat->code,
                'name' => $pusat->name,
                'parent_branch_id' => $cucu->id,
            ])
            ->assertSessionHasErrors('parent_branch_id');

        $this->assertNull($pusat->fresh()->parent_branch_id);
    }

    public function test_an_unused_branch_can_be_deleted(): void
    {
        $admin = $this->adminSistem();
        $branch = Branch::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.master.branches.destroy', $branch))
            ->assertRedirect(route('admin.master.branches.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    public function test_a_branch_that_still_holds_data_is_refused_instead_of_erroring(): void
    {
        $admin = $this->adminSistem();
        $branch = Branch::factory()->create();
        Member::factory()->create(['branch_id' => $branch->id]);

        $this->actingAs($admin)
            ->delete(route('admin.master.branches.destroy', $branch))
            ->assertRedirect(route('admin.master.branches.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('branches', ['id' => $branch->id]);
    }

    public function test_role_without_master_data_permission_cannot_open_the_branch_list(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('pengawas');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)
            ->get(route('admin.master.branches.index'))
            ->assertForbidden();
    }
}
