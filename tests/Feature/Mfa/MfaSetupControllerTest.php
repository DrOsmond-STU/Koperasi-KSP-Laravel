<?php

namespace Tests\Feature\Mfa;

use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alur self-service aktivasi MFA — user internal baru login, diarahkan ke
 * /mfa/setup oleh middleware mfa.required, di sana men-scan QR + memasukkan
 * kode dari authenticator app.
 */
class MfaSetupControllerTest extends TestCase
{
    use RefreshDatabase;

    private function internalUser(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => null]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    public function test_setup_page_renders_qr_code_and_secret(): void
    {
        $user = $this->internalUser();

        $response = $this->actingAs($user)->get(route('mfa.setup'));

        $response->assertOk();
        $response->assertViewIs('mfa.setup');
        $response->assertSee('Aktivasi Autentikasi Dua Faktor', escape: false);

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at, 'Belum boleh confirmed sebelum user memasukkan kode.');
    }

    public function test_setup_page_redirects_to_profile_when_mfa_already_confirmed(): void
    {
        $user = $this->internalUser();
        $user->forceFill([
            'two_factor_secret' => encrypt('SECRET'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)->get(route('mfa.setup'))
            ->assertRedirect(route('profile.edit'));
    }

    public function test_setup_page_is_accessible_even_though_user_would_normally_be_gated_by_mfa_middleware(): void
    {
        // Regresi: dulu middleware mfa.required abort(403) — sekarang harus REDIRECT,
        // dan /mfa/setup sendiri tidak boleh masuk grup mfa.required (kalau iya, loop).
        $user = $this->internalUser();

        $this->actingAs($user)->get(route('mfa.setup'))->assertOk();
    }

    public function test_regenerate_clears_existing_secret_and_generates_new_one(): void
    {
        $user = $this->internalUser();
        $this->actingAs($user)->get(route('mfa.setup'))->assertOk();
        $firstSecret = $user->fresh()->two_factor_secret;

        $this->actingAs($user)->post(route('mfa.setup.regenerate'))
            ->assertRedirect(route('mfa.setup'));

        // Setelah regenerate, kunjungi setup lagi supaya secret baru dibuat.
        $this->actingAs($user)->get(route('mfa.setup'));
        $secondSecret = $user->fresh()->two_factor_secret;

        $this->assertNotNull($secondSecret);
        $this->assertNotEquals($firstSecret, $secondSecret);
    }

    public function test_regenerate_is_noop_when_mfa_already_confirmed(): void
    {
        $user = $this->internalUser();
        $user->forceFill([
            'two_factor_secret' => encrypt('KEEP-ME'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)->post(route('mfa.setup.regenerate'))
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh()->two_factor_secret);
    }

    public function test_confirm_with_invalid_code_leaves_two_factor_unconfirmed(): void
    {
        $user = $this->internalUser();
        $this->actingAs($user)->get(route('mfa.setup'));

        $this->actingAs($user)->post(route('mfa.setup.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors(['code'], null, 'confirmTwoFactorAuthentication');

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }
}
