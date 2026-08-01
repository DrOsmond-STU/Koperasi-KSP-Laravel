<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 03_DESIGN.md "Sidebar Nav" — navigasi berkelompok yang menyembunyikan
 * item sesuai permission user (bukan sekadar role), dan A11Y-05 (mobile
 * drawer) dari 06_TESTING.md §14.
 */
class SidebarNavTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_sidebar_only_shows_items_the_user_has_permission_for(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->givePermissionTo('notifikasi_log.read');

        $response = $this->actingAs($user)->get(route('admin.notifikasi.log.index'));

        $response->assertOk();
        $response->assertSee('Log Notifikasi');
        $response->assertDontSee('Jurnal &amp; Buku Besar', false);
        $response->assertDontSee('Report Builder');
    }

    public function test_sidebar_always_shows_items_without_a_permission_requirement(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->givePermissionTo('notifikasi_log.read');

        $response = $this->actingAs($user)->get(route('admin.notifikasi.log.index'));

        $response->assertSee('Dashboard Utama');
    }

    public function test_admin_sistem_sees_every_group_as_superuser(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('admin_sistem');

        $response = $this->actingAs($user)->get(route('admin.notifikasi.log.index'));

        $response->assertSee('Pengaturan Branding');
        $response->assertSee('Saldo Awal');
    }

    public function test_mobile_drawer_toggle_markup_is_present(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->givePermissionTo('notifikasi_log.read');

        $response = $this->actingAs($user)->get(route('admin.notifikasi.log.index'));

        $response->assertSee('id="sidebar-toggle-btn"', false);
        $response->assertSee('aria-controls="sidebar-nav"', false);
    }
}
