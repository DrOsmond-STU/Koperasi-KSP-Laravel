<?php

namespace Tests\Feature\SecurityAudit;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md acceptance for Task 5.5: Manajer/Bendahara terbatas
 * (bisa melihat, tidak bisa ekspor), Pengawas penuh (bisa keduanya).
 */
class SecurityAuditControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manajer_can_view_but_not_export_audit_log(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        $this->actingAs($user)->get(route('admin.keamanan-audit.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.keamanan-audit.log'))->assertOk();
        $this->actingAs($user)->get(route('admin.keamanan-audit.export'))->assertForbidden();
    }

    public function test_bendahara_can_view_but_not_export_audit_log(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('bendahara');

        $this->actingAs($user)->get(route('admin.keamanan-audit.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.keamanan-audit.export'))->assertForbidden();
    }

    public function test_pengawas_has_full_access_including_export(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('pengawas');

        $this->actingAs($user)->get(route('admin.keamanan-audit.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.keamanan-audit.log'))->assertOk();
        $this->actingAs($user)->get(route('admin.keamanan-audit.consent'))->assertOk();

        $response = $this->actingAs($user)->get(route('admin.keamanan-audit.export'));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_role_without_permission_cannot_view_at_all(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');

        $this->actingAs($user)->get(route('admin.keamanan-audit.index'))->assertForbidden();
    }
}
