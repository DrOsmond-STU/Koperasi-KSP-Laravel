<?php

use App\Http\Controllers\Admin\ChartOfAccountImportController;
use Illuminate\Support\Facades\Route;

/*
| Import massal Bagan Akun. File terpisah agar routes/web.php tidak
| tersentuh; didaftarkan lewat `then:` pada withRouting() di bootstrap/app.php.
| Segmen "import" tidak bentrok dengan route {chartOfAccount} bawaan karena
| route bawaan hanya punya bentuk /{chartOfAccount}/edit dan tanpa segmen ini.
*/

Route::middleware(['web', 'auth', 'active.user', 'mfa.required'])->group(function () {
    Route::get('/admin/master/bagan-akun/import', [ChartOfAccountImportController::class, 'form'])
        ->name('admin.master.chart-of-accounts.import.form');

    Route::post('/admin/master/bagan-akun/import', [ChartOfAccountImportController::class, 'import'])
        ->name('admin.master.chart-of-accounts.import');
});
