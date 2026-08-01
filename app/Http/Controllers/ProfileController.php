<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Profil pengguna sendiri — "menyambungkan langkah terakhir" ke endpoint
 * Fortify yang sudah aktif (user-profile-information.update,
 * user-password.update) tapi belum punya halaman GET (Fortify::views()
 * sengaja false, lihat config/fortify.php). Berlaku untuk SEMUA user login,
 * tidak digate permission apa pun — setiap orang boleh mengubah profilnya
 * sendiri.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }
}
