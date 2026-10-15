<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SECURITY.md §Authentication / 06_TESTING.md SES-01: role internal (Teller,
 * Petugas Kredit, Petugas UPF, Bendahara, Manajer/Pengurus, Pengawas, Admin
 * Sistem) tidak boleh mengakses area terproteksi tanpa MFA aktif — ini
 * berbeda dari alur "verifikasi 2FA saat login" bawaan Fortify, yang hanya
 * menegakkan MFA JIKA sudah diaktifkan. Middleware ini menutup celah:
 * user berperan internal yang belum pernah mengaktifkan 2FA tetap diblok.
 */
class EnsureMfaIsEnabledForInternalRoles
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Kewajiban MFA bisa dimatikan lewat konfigurasi. Alasannya panjang dan
        // ditulis di config/koperasi.php: halaman tantangan 2FA tidak pernah
        // dibangun, sehingga menegakkan kewajiban ini justru mengunci pengguna
        // dari sistem tanpa jalan keluar. Middleware sengaja dibiarkan terpasang
        // di rute supaya bisa dihidupkan lagi dengan satu setelan.
        if (! config('koperasi.wajib_mfa', true)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && $user->requiresMfa() && ! $user->two_factor_confirmed_at) {
            abort(403, 'Aktifkan autentikasi dua faktor (MFA) sebelum melanjutkan. Hubungi Admin Sistem bila perlu bantuan.');
        }

        return $next($request);
    }
}
