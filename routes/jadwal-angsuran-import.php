<?php

use App\Http\Controllers\Admin\LoanRepaymentHistoryImportController;
use App\Http\Controllers\Admin\LoanScheduleImportController;
use Illuminate\Support\Facades\Route;

/*
| Import massal Jadwal Angsuran & Riwayat Pembayaran untuk pinjaman yang sudah
| ada. File terpisah agar routes/web.php tidak tersentuh; didaftarkan lewat
| `then:` pada withRouting() di bootstrap/app.php.
|
| Ditaruh di bawah /admin/pinjaman/{segmen}/import supaya tidak bertabrakan
| dengan route {loan} milik Persetujuan Pinjaman.
*/

Route::middleware(['web', 'auth', 'active.user', 'mfa.required'])->group(function () {
    Route::get('/admin/pinjaman/jadwal-angsuran/import', [LoanScheduleImportController::class, 'form'])
        ->name('admin.pinjaman.jadwal.import.form');

    Route::post('/admin/pinjaman/jadwal-angsuran/import', [LoanScheduleImportController::class, 'import'])
        ->name('admin.pinjaman.jadwal.import');

    Route::get('/admin/pinjaman/historis-pembayaran/import', [LoanRepaymentHistoryImportController::class, 'form'])
        ->name('admin.pinjaman.historis.import.form');

    Route::post('/admin/pinjaman/historis-pembayaran/import', [LoanRepaymentHistoryImportController::class, 'import'])
        ->name('admin.pinjaman.historis.import');
});
