<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

/**
 * Wizard aktivasi MFA — halaman self-service tempat user (biasanya baru
 * login pertama kali dengan role internal) memindai QR TOTP dan memasukkan
 * 6 digit kode konfirmasi. Middleware mfa.required sengaja TIDAK dipasang
 * di sini supaya user yang belum aktif MFA bisa mencapai halamannya
 * (kalau tidak, redirect loop). Fortify's action classes dipanggil langsung
 * sehingga wizard tidak lewat gate `password.confirm` bawaan Fortify —
 * user baru saja memasukkan password waktu login, jadi konfirmasi ulang
 * tidak diperlukan di alur onboarding ini.
 *
 * Halaman ini juga menjadi tempat user yang MFA-nya di-reset oleh Admin
 * Sistem (UserController::resetMfa) mengulang setup dari awal.
 */
class MfaSetupController extends Controller
{
    /**
     * Tampilkan wizard. Kalau user belum punya `two_factor_secret`,
     * generate secret + recovery codes sekali di sini supaya QR code
     * langsung bisa dirender tanpa langkah "Enable" terpisah.
     */
    public function show(Request $request, EnableTwoFactorAuthentication $enable): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return redirect()->route('profile.edit')
                ->with('status', 'Autentikasi dua faktor sudah aktif.');
        }

        if (! $user->two_factor_secret) {
            $enable($user);
            $user->refresh();
        }

        return view('mfa.setup', [
            'user' => $user,
            'qrCodeSvg' => $user->twoFactorQrCodeSvg(),
            'secretKey' => decrypt($user->two_factor_secret),
        ]);
    }

    /**
     * Konfirmasi setup dengan kode 6 digit dari aplikasi authenticator.
     * Fortify's action menaruh error validasi ke session error bag
     * `confirmTwoFactorAuthentication` bila kode salah.
     */
    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $confirm($request->user(), $request->input('code'));

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Autentikasi dua faktor berhasil diaktifkan.');
    }

    /**
     * Batalkan setup yang sedang berjalan dan mulai ulang dari awal —
     * berguna kalau user salah scan QR atau ganti aplikasi authenticator.
     * Tidak menyentuh user yang MFA-nya sudah confirmed (arahkan ke
     * halaman profil bila mereka ingin nonaktifkan).
     */
    public function regenerate(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return redirect()->route('profile.edit');
        }

        $disable($user);

        return redirect()->route('mfa.setup');
    }
}
