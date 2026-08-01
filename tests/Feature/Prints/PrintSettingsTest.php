<?php

namespace Tests\Feature\Prints;

use App\Models\PrintSetting;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Pengaturan Cetakan" — kertas/margin/font/kop dipakai bersama oleh SEMUA
 * cetakan baru (prints/layout.blade.php), diatur sekali di sini. Gate:
 * cetakan.manage (admin_sistem saja, sama seperti branding.manage).
 */
class PrintSettingsTest extends TestCase
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

    public function test_role_without_cetakan_manage_cannot_view_settings(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)->get(route('admin.pengaturan.cetakan.edit'))->assertForbidden();
    }

    public function test_admin_sistem_can_view_and_update_print_settings(): void
    {
        $admin = $this->adminSistem();

        $this->actingAs($admin)->get(route('admin.pengaturan.cetakan.edit'))->assertOk();

        $response = $this->actingAs($admin)->put(route('admin.pengaturan.cetakan.update'), [
            'paper_size' => 'f4',
            'orientation' => 'landscape',
            'margin_mm' => 20,
            'font_size_pt' => 12,
            'show_address' => '1',
            'address_text' => 'Jl. Uji No. 1',
            'footer_text' => 'Dicetak otomatis',
        ]);

        $response->assertRedirect();

        $setting = PrintSetting::query()->first();
        $this->assertEquals('f4', $setting->paper_size);
        $this->assertEquals('landscape', $setting->orientation);
        $this->assertEquals(20, $setting->margin_mm);
        $this->assertTrue($setting->show_address);
        $this->assertEquals('Jl. Uji No. 1', $setting->address_text);
    }

    public function test_default_settings_are_created_on_first_access(): void
    {
        $admin = $this->adminSistem();

        $this->actingAs($admin)->get(route('admin.pengaturan.cetakan.edit'))->assertOk();

        $this->assertDatabaseHas('print_settings', ['id' => 1, 'paper_size' => 'a4']);
    }
}
