<?php

namespace Tests\Feature\Admin;

use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        return $user;
    }

    public function test_index_and_create_are_gated_not_just_store(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('anggota');

        $this->actingAs($user)->get(route('admin.master.member-types.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.master.member-types.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.master.member-types.store'), [])->assertForbidden();
    }

    public function test_creating_member_type_succeeds(): void
    {
        $user = $this->manajer();

        $response = $this->actingAs($user)->post(route('admin.master.member-types.store'), [
            'code' => 'MITRA',
            'name' => 'Anggota Mitra',
        ]);

        $response->assertRedirect(route('admin.master.member-types.index'));
        $this->assertDatabaseHas('member_types', ['code' => 'MITRA', 'name' => 'Anggota Mitra', 'is_active' => 1]);
    }

    public function test_deleting_unused_member_type_succeeds(): void
    {
        $user = $this->manajer();
        $type = MemberType::factory()->create();

        $response = $this->actingAs($user)->delete(route('admin.master.member-types.destroy', $type));

        $response->assertRedirect(route('admin.master.member-types.index'));
        $this->assertDatabaseMissing('member_types', ['id' => $type->id]);
    }

    public function test_deleting_member_type_in_use_is_rejected(): void
    {
        $user = $this->manajer();
        $type = MemberType::factory()->create();
        Member::factory()->create(['member_type_id' => $type->id]);

        $response = $this->actingAs($user)->delete(route('admin.master.member-types.destroy', $type));

        $response->assertRedirect(route('admin.master.member-types.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('member_types', ['id' => $type->id]);
    }

    public function test_updating_member_type_succeeds(): void
    {
        $user = $this->manajer();
        $type = MemberType::factory()->create(['name' => 'Nama Lama']);

        $response = $this->actingAs($user)->put(route('admin.master.member-types.update', $type), [
            'code' => $type->code,
            'name' => 'Nama Baru',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.master.member-types.index'));
        $this->assertDatabaseHas('member_types', ['id' => $type->id, 'name' => 'Nama Baru']);
    }
}
