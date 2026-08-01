<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun yang dinonaktifkan Admin Sistem (UserController::deactivate()) tidak
 * boleh dipakai lagi — tidak ada hard-delete user di aplikasi ini (created_by
 * pada 30+ tabel pakai restrictOnDelete()), jadi "tidak bisa login" ditegakkan
 * di sini: sesi yang masih terbuka saat akun dinonaktifkan langsung ditutup
 * paksa pada request berikutnya, bukan hanya diblok saat proses login baru.
 */
class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'Akun Anda telah dinonaktifkan. Hubungi Admin Sistem.');
        }

        return $next($request);
    }
}
