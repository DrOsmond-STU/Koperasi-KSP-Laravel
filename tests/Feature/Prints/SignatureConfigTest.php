<?php

namespace Tests\Feature\Prints;

use App\Models\DocumentSignatory;
use App\Models\DocumentSignatureSlot;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Setup Tanda Tangan" — daftar penanda tangan (nama+jabatan) TIDAK diikat
 * ke akun login/RBAC, plus slot tanda tangan per kelompok dokumen.
 */
class SignatureConfigTest extends TestCase
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

    public function test_index_auto_seeds_default_slots_per_document_group(): void
    {
        $admin = $this->adminSistem();

        $this->actingAs($admin)->get(route('admin.pengaturan.tanda-tangan.index'))->assertOk();

        $this->assertDatabaseHas('document_signature_slots', ['document_group' => 'jurnal_umum']);
        $this->assertDatabaseHas('document_signature_slots', ['document_group' => 'pengajuan_pinjaman']);
        $this->assertEquals(10, DocumentSignatureSlot::query()->distinct('document_group')->count('document_group'));
    }

    public function test_admin_can_add_and_update_a_signatory(): void
    {
        $admin = $this->adminSistem();

        $this->actingAs($admin)->post(route('admin.pengaturan.tanda-tangan.signatory.store'), [
            'name' => 'Budi Santoso',
            'title' => 'Ketua Koperasi',
        ])->assertRedirect();

        $signatory = DocumentSignatory::query()->where('name', 'Budi Santoso')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.pengaturan.tanda-tangan.signatory.update', $signatory), [
            'name' => 'Budi Santoso Update',
            'title' => 'Ketua Koperasi Baru',
        ])->assertRedirect();

        $this->assertEquals('Budi Santoso Update', $signatory->fresh()->name);
    }

    public function test_admin_can_assign_a_signatory_to_a_slot(): void
    {
        $admin = $this->adminSistem();
        $this->actingAs($admin)->get(route('admin.pengaturan.tanda-tangan.index'));

        $signatory = DocumentSignatory::query()->create(['name' => 'Siti Aminah', 'title' => 'Bendahara']);
        $slot = DocumentSignatureSlot::query()->where('document_group', 'jurnal_umum')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.pengaturan.tanda-tangan.slot.update', $slot), [
            'label' => 'Bendahara',
            'document_signatory_id' => $signatory->id,
        ])->assertRedirect();

        $this->assertEquals($signatory->id, $slot->fresh()->document_signatory_id);
        $this->assertEquals('Bendahara', $slot->fresh()->label);
    }

    public function test_role_without_cetakan_manage_cannot_manage_signatures(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)->get(route('admin.pengaturan.tanda-tangan.index'))->assertForbidden();
    }
}
