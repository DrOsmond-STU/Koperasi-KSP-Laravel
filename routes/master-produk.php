<?php

use App\Http\Controllers\Admin\LoanProductController;
use App\Http\Controllers\Admin\SavingsProductController;
use Illuminate\Support\Facades\Route;

/*
| Ubah / aktifkan / hapus Produk Simpanan & Produk Pinjaman. File terpisah
| agar routes/web.php tidak tersentuh; didaftarkan lewat `then:` pada
| withRouting() di bootstrap/app.php.
|
| Tidak ada bentrokan dengan web.php: di sana kedua master hanya punya
| index, create ("/tambah"), dan store — belum ada satu pun route ber-parameter,
| jadi segmen {savingsProduct}/{loanProduct} di sini tidak menaungi apa pun
| yang sudah terdaftar lebih dulu.
*/

Route::middleware(['web', 'auth', 'active.user', 'mfa.required'])->group(function () {
    Route::get('/admin/master/produk-simpanan/{savingsProduct}/ubah', [SavingsProductController::class, 'edit'])
        ->name('admin.master.savings-products.edit');
    Route::put('/admin/master/produk-simpanan/{savingsProduct}', [SavingsProductController::class, 'update'])
        ->name('admin.master.savings-products.update');
    Route::post('/admin/master/produk-simpanan/{savingsProduct}/aktif', [SavingsProductController::class, 'toggleActive'])
        ->name('admin.master.savings-products.toggle-active');
    Route::delete('/admin/master/produk-simpanan/{savingsProduct}', [SavingsProductController::class, 'destroy'])
        ->name('admin.master.savings-products.destroy');

    Route::get('/admin/master/produk-pinjaman/{loanProduct}/ubah', [LoanProductController::class, 'edit'])
        ->name('admin.master.loan-products.edit');
    Route::put('/admin/master/produk-pinjaman/{loanProduct}', [LoanProductController::class, 'update'])
        ->name('admin.master.loan-products.update');
    Route::post('/admin/master/produk-pinjaman/{loanProduct}/aktif', [LoanProductController::class, 'toggleActive'])
        ->name('admin.master.loan-products.toggle-active');
    Route::delete('/admin/master/produk-pinjaman/{loanProduct}', [LoanProductController::class, 'destroy'])
        ->name('admin.master.loan-products.destroy');
});
