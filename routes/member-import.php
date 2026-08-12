<?php

use App\Http\Controllers\Admin\MemberImportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Import Massal Master Anggota
|--------------------------------------------------------------------------
|
| Dipisah dari routes/web.php supaya penambahan fitur ini tidak menyentuh
| file route utama sama sekali (lebih mudah dibatalkan). Didaftarkan lewat
| `then:` pada withRouting() di bootstrap/app.php.
|
| Middleware sengaja ditulis lengkap termasuk 'web', karena route yang
| didaftarkan lewat `then:` tidak otomatis masuk grup mana pun.
|
| Tidak ada bentrokan dengan routes/web.php: di sana tidak ada route GET
| '/admin/anggota/{member}' bersegmen tunggal, jadi 'import' tidak akan
| tertangkap sebagai parameter {member}.
|
*/

Route::middleware(['web', 'auth', 'active.user', 'mfa.required'])->group(function () {
    Route::get('/admin/anggota/import', [MemberImportController::class, 'form'])
        ->name('admin.members.import.form');

    Route::get('/admin/anggota/import/template', [MemberImportController::class, 'downloadTemplate'])
        ->name('admin.members.import.template');

    Route::post('/admin/anggota/import', [MemberImportController::class, 'import'])
        ->name('admin.members.import');
});
