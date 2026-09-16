<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wizard aktivasi MFA harus bisa dicapai.
 *
 * MfaSetupController dan resources/views/mfa/setup.blade.php sudah ada sejak
 * lama, tapi rutenya tidak pernah didaftarkan. Akibatnya
 * profile/edit.blade.php memanggil route('mfa.setup') yang tidak ada, dan
 * halaman Profil balas HTTP 500 untuk setiap pengguna yang MFA-nya belum
 * aktif — 9 kali pada 15 Sep 2026 di produksi.
 *
 * Yang dikunci di sini bukan cuma "rutenya ada", tapi juga bahwa halaman
 * Profil benar-benar bisa dibuka oleh pengguna tanpa MFA. Itu jalur yang
 * rusak, dan tanpa mengujinya rute bisa saja terdaftar tapi halamannya tetap
 * jatuh karena sebab lain.
 */
class MfaSetupRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function tanpaMfa(): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => null]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    public function test_the_profile_page_opens_for_a_user_without_mfa(): void
    {
        $this->actingAs($this->tanpaMfa())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Aktifkan sekarang', false);
    }

    public function test_the_setup_wizard_is_reachable(): void
    {
        $this->actingAs($this->tanpaMfa())
            ->get(route('mfa.setup'))
            ->assertOk();
    }

    /**
     * Rutenya sengaja di luar grup `mfa.required`. Kalau suatu saat ikut
     * masuk grup itu, pengguna yang belum aktif MFA akan diblok dari
     * satu-satunya halaman yang bisa mengaktifkannya — terkunci tanpa jalan
     * keluar, dan uji ini yang menangkapnya.
     */
    public function test_the_wizard_is_not_behind_the_mfa_requirement(): void
    {
        config(['koperasi.wajib_mfa' => true]);

        $this->actingAs($this->tanpaMfa())
            ->get(route('mfa.setup'))
            ->assertOk();
    }

    public function test_a_user_who_already_has_mfa_is_sent_back_to_the_profile(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)
            ->get(route('mfa.setup'))
            ->assertRedirect(route('profile.edit'));
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('mfa.setup'))->assertRedirect(route('login'));
    }
}
