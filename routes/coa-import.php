<?php

use App\Http\Controllers\Admin\ChartOfAccountImportController;
use App\Http\Controllers\Admin\ChartOfAccountPurgeController;
use Illuminate\Support\Facades\Route;

/*
| Import massal & hapus massal Bagan Akun. File terpisah agar routes/web.php
| tidak tersentuh; didaftarkan lewat `then:` pada withRouting() di
| bootstrap/app.php.
|
| Segmen "import" dan "hapus-massal" tidak bentrok dengan route
| {chartOfAccount} bawaan: satu-satunya route bawaan berpola
| /admin/master/bagan-akun/{segmen} adalah DELETE (destroy). Karena itu
| penghapusan massal memakai POST, bukan DELETE — route file ini dimuat
| SESUDAH web.php, jadi DELETE /hapus-massal akan lebih dulu tertangkap
| destroy dan gagal saat mencoba mengikat "hapus-massal" sebagai model.
*/

Route::middleware(['web', 'auth', 'active.user', 'mfa.required'])->group(function () {
    Route::get('/admin/master/bagan-akun/import', [ChartOfAccountImportController::class, 'form'])
        ->name('admin.master.chart-of-accounts.import.form');

    Route::post('/admin/master/bagan-akun/import', [ChartOfAccountImportController::class, 'import'])
        ->name('admin.master.chart-of-accounts.import');

    Route::get('/admin/master/bagan-akun/hapus-massal', [ChartOfAccountPurgeController::class, 'form'])
        ->name('admin.master.chart-of-accounts.purge.form');

    Route::post('/admin/master/bagan-akun/hapus-massal', [ChartOfAccountPurgeController::class, 'destroy'])
        ->name('admin.master.chart-of-accounts.purge');
});
