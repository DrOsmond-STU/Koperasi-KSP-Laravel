<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberControllerTest extends TestCase
{
    use RefreshDatabase;

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    public function test_index_and_create_are_gated_not_just_store(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('anggota');

        $this->actingAs($user)->get(route('admin.members.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.members.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.members.store'), [])->assertForbidden();
    }

    public function test_creating_member_with_valid_data_succeeds(): void
    {
        $user = $this->manajer();
        $branch = Branch::factory()->create();
        $memberType = MemberType::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.members.store'), [
            'branch_id' => $branch->id,
            'member_type_id' => $memberType->id,
            'member_number' => 'AGT-0001',
            'name' => 'Budi Santoso',
            'status' => 'aktif',
        ]);

        $response->assertRedirect(route('admin.members.index'));
        $this->assertDatabaseHas('members', ['member_number' => 'AGT-0001', 'name' => 'Budi Santoso']);
    }

    public function test_duplicate_member_number_is_rejected(): void
    {
        $user = $this->manajer();
        $branch = Branch::factory()->create();
        $memberType = MemberType::factory()->create();
        Member::factory()->create(['member_number' => 'AGT-0002']);

        $response = $this->actingAs($user)->post(route('admin.members.store'), [
            'branch_id' => $branch->id,
            'member_type_id' => $memberType->id,
            'member_number' => 'AGT-0002',
            'name' => 'Siti Aminah',
            'status' => 'aktif',
        ]);

        $response->assertSessionHasErrors(['member_number']);
    }

    public function test_updating_member_succeeds_and_ignores_own_member_number(): void
    {
        $user = $this->manajer();
        $member = Member::factory()->create(['member_number' => 'AGT-0003']);

        $response = $this->actingAs($user)->put(route('admin.members.update', $member), [
            'branch_id' => $member->branch_id,
            'member_type_id' => $member->member_type_id,
            'member_number' => 'AGT-0003',
            'name' => 'Nama Baru',
            'status' => 'aktif',
        ]);

        $response->assertRedirect(route('admin.members.index'));
        $this->assertDatabaseHas('members', ['id' => $member->id, 'name' => 'Nama Baru']);
    }

    public function test_uploading_photo_on_create_stores_file_and_path(): void
    {
        Storage::fake('public');
        $user = $this->manajer();
        $branch = Branch::factory()->create();
        $memberType = MemberType::factory()->create();
        $photo = UploadedFile::fake()->image('foto.jpg');

        $this->actingAs($user)->post(route('admin.members.store'), [
            'branch_id' => $branch->id,
            'member_type_id' => $memberType->id,
            'member_number' => 'AGT-0004',
            'name' => 'Punya Foto',
            'status' => 'aktif',
            'photo' => $photo,
        ]);

        $member = Member::query()->where('member_number', 'AGT-0004')->firstOrFail();
        $this->assertNotNull($member->photo_path);
        Storage::disk('public')->assertExists($member->photo_path);
    }

    public function test_uploading_new_photo_on_update_replaces_old_file(): void
    {
        Storage::fake('public');
        $user = $this->manajer();
        $oldPath = UploadedFile::fake()->image('lama.jpg')->store('members', 'public');
        $member = Member::factory()->create(['photo_path' => $oldPath]);
        $newPhoto = UploadedFile::fake()->image('baru.jpg');

        $this->actingAs($user)->put(route('admin.members.update', $member), [
            'branch_id' => $member->branch_id,
            'member_type_id' => $member->member_type_id,
            'member_number' => $member->member_number,
            'name' => $member->name,
            'status' => 'aktif',
            'photo' => $newPhoto,
        ]);

        Storage::disk('public')->assertMissing($oldPath);
        $this->assertNotEquals($oldPath, $member->fresh()->photo_path);
        Storage::disk('public')->assertExists($member->fresh()->photo_path);
    }

    public function test_print_list_filters_by_member_type(): void
    {
        $user = $this->manajer();
        $typeA = MemberType::factory()->create();
        $typeB = MemberType::factory()->create();
        Member::factory()->create(['member_type_id' => $typeA->id, 'name' => 'Anggota A']);
        Member::factory()->create(['member_type_id' => $typeB->id, 'name' => 'Anggota B']);

        $response = $this->actingAs($user)->get(route('admin.members.print', ['member_type_id' => $typeA->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_print_list_respects_branch_scope(): void
    {
        $ownBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $this->seed(RolePermissionSeeder::class);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'single', 'single_branch_id' => $ownBranch->id]);

        $response = $this->actingAs($user)->get(route('admin.members.print', ['branch_id' => $otherBranch->id]));

        $response->assertForbidden();
    }
}
