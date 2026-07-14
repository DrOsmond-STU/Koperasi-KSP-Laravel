<?php

namespace Tests\Feature\Calendar;

use App\Models\CooperativeEvent;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 08_TASK_INSTRUCTION.md Task 4.1 acceptance: CRUD kegiatan mengikuti
 * matriks otorisasi (PRD §5/§15.1).
 */
class CooperativeEventControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manajer_can_create_an_event(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        $response = $this->actingAs($user)->post(route('admin.kalender.kegiatan.store'), [
            'title' => 'Rapat Anggota',
            'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDay()->addHours(2)->format('Y-m-d H:i:s'),
            'branch_scope_type' => 'all',
            'participant_type' => 'semua_anggota_cabang',
        ]);

        $response->assertRedirect(route('admin.kalender.kegiatan.index'));
        $this->assertDatabaseHas('cooperative_events', ['title' => 'Rapat Anggota']);
    }

    public function test_role_without_permission_cannot_create_event(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');

        $response = $this->actingAs($user)->post(route('admin.kalender.kegiatan.store'), [
            'title' => 'Rapat Anggota',
            'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDay()->addHours(2)->format('Y-m-d H:i:s'),
            'branch_scope_type' => 'all',
            'participant_type' => 'semua_anggota_cabang',
        ]);

        $response->assertForbidden();
    }

    public function test_role_without_permission_cannot_cancel_event(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $manajer = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $manajer->assignRole('manajer');
        $event = CooperativeEvent::factory()->create();

        $teller = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $teller->assignRole('teller');

        $response = $this->actingAs($teller)->post(route('admin.kalender.kegiatan.cancel', $event));

        $response->assertForbidden();
    }
}
