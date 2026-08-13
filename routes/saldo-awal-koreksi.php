<?php

use App\Http\Controllers\Admin\OpeningBalanceCorrectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Koreksi Data Saldo Awal
|--------------------------------------------------------------------------
|
| File terpisah agar routes/web.php tidak tersentuh; didaftarkan lewat
| `then:` pada withRouting() di bootstrap/app.php. Middleware ditulis
| lengkap termasuk 'web' karena route dari `then:` tidak masuk grup mana pun.
|
*/

Route::middleware(['web', 'auth', 'active.user', 'mfa.required'])->group(function () {
    Route::get('/admin/saldo-awal/{batch}/koreksi', [OpeningBalanceCorrectionController::class, 'index'])
        ->name('admin.saldo-awal.koreksi');

    Route::post('/admin/saldo-awal/{batch}/koreksi/{subModule}/kosongkan', [OpeningBalanceCorrectionController::class, 'clear'])
        ->name('admin.saldo-awal.koreksi.clear');
});
