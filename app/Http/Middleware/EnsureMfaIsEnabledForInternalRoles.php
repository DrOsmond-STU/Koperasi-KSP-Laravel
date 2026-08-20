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
 *
 * Alur: user yang wajib MFA tapi belum setup diarahkan ke wizard
 * /mfa/setup — bukan di-403, supaya mereka bisa mengaktifkan MFA sendiri
 * (scan QR + input kode) sebelum akhirnya dilepas ke sistem. Route
 * mfa.setup sengaja TIDAK dilindungi middleware ini (lihat routes/web.php)
 * agar tidak terjadi redirect loop.
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
        $user = $request->user();

        if ($user && $user->needsMfaSetup()) {
            return redirect()
                ->route('mfa.setup')
                ->with('status', 'Aktifkan autentikasi dua faktor (MFA) untuk melanjutkan.');
        }

        return $next($request);
    }
}
