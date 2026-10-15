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
| Segmen setelah {batch} selalu diawali literal "koreksi", jadi tidak pernah
| bentrok dengan route bawaan /admin/saldo-awal/{batch} di routes/web.php.
|
*/

Route::middleware(['web', 'auth', 'active.user', 'mfa.required'])->group(function () {
    Route::get('/admin/saldo-awal/{batch}/koreksi', [OpeningBalanceCorrectionController::class, 'index'])
        ->name('admin.saldo-awal.koreksi');

    Route::get('/admin/saldo-awal/{batch}/koreksi/{subModule}', [OpeningBalanceCorrectionController::class, 'rows'])
        ->name('admin.saldo-awal.koreksi.rows');

    Route::post('/admin/saldo-awal/{batch}/koreksi/{subModule}/kosongkan', [OpeningBalanceCorrectionController::class, 'clear'])
        ->name('admin.saldo-awal.koreksi.clear');

    Route::get('/admin/saldo-awal/{batch}/koreksi/{subModule}/{rowId}/ubah', [OpeningBalanceCorrectionController::class, 'editRow'])
        ->whereNumber('rowId')
        ->name('admin.saldo-awal.koreksi.edit');

    Route::put('/admin/saldo-awal/{batch}/koreksi/{subModule}/{rowId}', [OpeningBalanceCorrectionController::class, 'updateRow'])
        ->whereNumber('rowId')
        ->name('admin.saldo-awal.koreksi.update');
});
