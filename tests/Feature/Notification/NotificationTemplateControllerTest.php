<?php

namespace Tests\Feature\Notification;

use App\Models\NotificationTemplate;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sistem_can_update_a_template(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('admin_sistem');
        $template = NotificationTemplate::factory()->create();

        $response = $this->actingAs($user)->put(route('admin.notifikasi.template.update', $template), [
            'body' => 'Pesan baru untuk {nama_anggota}.',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.notifikasi.template.index'));
        $this->assertDatabaseHas('notification_templates', ['id' => $template->id, 'body' => 'Pesan baru untuk {nama_anggota}.']);
    }

    public function test_role_without_permission_cannot_update_template(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        $template = NotificationTemplate::factory()->create();

        $response = $this->actingAs($user)->put(route('admin.notifikasi.template.update', $template), [
            'body' => 'Coba ubah.',
        ]);

        $response->assertForbidden();
    }
}
