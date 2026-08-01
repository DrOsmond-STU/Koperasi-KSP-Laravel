<?php

namespace Tests\Feature\Profile;

use App\Models\Member;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * "Menyambungkan langkah terakhir" ke endpoint Fortify yang sudah aktif
 * (user-profile-information.update, user-password.update) — halaman
 * /profil-saya cukup merender form yang POST ke rute tersebut.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_own_profile_page(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee($user->email);
    }

    /**
     * Regression: anggota-only accounts were previously dropped into the
     * admin sidebar layout when visiting Profil Saya from Portal Anggota —
     * the page must stay inside the portal chrome (5-menu nav), never show
     * the admin "Dashboard Utama" sidebar.
     */
    public function test_anggota_only_user_sees_portal_layout_on_profile_page(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $member = Member::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('anggota');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);
        $member->update(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('Navigasi Portal Anggota', false);
        $response->assertDontSee('Dashboard Utama');
    }

    public function test_staff_user_sees_admin_layout_on_profile_page(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertDontSee('Navigasi Portal Anggota', false);
    }

    public function test_guest_cannot_view_profile_page(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_user_can_update_own_name_and_email(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);

        $this->actingAs($user)->put(route('user-profile-information.update'), [
            'name' => 'Nama Baru',
            'email' => $user->email,
        ])->assertSessionHas('status', 'profile-information-updated');

        $this->assertEquals('Nama Baru', $user->fresh()->name);
    }

    public function test_user_can_change_own_password_with_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password-lama'),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($user)->put(route('user-password.update'), [
            'current_password' => 'password-lama',
            'password' => 'Qzt7mXrPd4nK',
            'password_confirmation' => 'Qzt7mXrPd4nK',
        ])->assertSessionHas('status', 'password-updated');

        $this->assertTrue(Hash::check('Qzt7mXrPd4nK', $user->fresh()->password));
    }

    public function test_changing_password_with_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password-lama'),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($user)->put(route('user-password.update'), [
            'current_password' => 'password-salah',
            'password' => 'Qzt7mXrPd4nK',
            'password_confirmation' => 'Qzt7mXrPd4nK',
        ])->assertSessionHasErrorsIn('updatePassword', ['current_password']);

        $this->assertTrue(Hash::check('password-lama', $user->fresh()->password));
    }
}
