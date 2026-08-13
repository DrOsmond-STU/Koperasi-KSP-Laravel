<?php

use App\Http\Controllers\Admin\SavingsProductCategoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Master Kategori Produk Simpanan
|--------------------------------------------------------------------------
|
| Ditaruh di file terpisah agar routes/web.php tidak tersentuh, lalu
| didaftarkan lewat `then:` pada withRouting() di bootstrap/app.php.
| Middleware ditulis lengkap termasuk 'web' karena route yang didaftarkan
| lewat `then:` tidak otomatis masuk grup mana pun.
|
*/

Route::middleware(['web', 'auth', 'active.user', 'mfa.required'])->group(function () {
    Route::get('/admin/master/kategori-simpanan', [SavingsProductCategoryController::class, 'index'])
        ->name('admin.master.savings-product-categories.index');

    Route::get('/admin/master/kategori-simpanan/tambah', [SavingsProductCategoryController::class, 'create'])
        ->name('admin.master.savings-product-categories.create');

    Route::post('/admin/master/kategori-simpanan', [SavingsProductCategoryController::class, 'store'])
        ->name('admin.master.savings-product-categories.store');

    Route::get('/admin/master/kategori-simpanan/{savingsProductCategory}/edit', [SavingsProductCategoryController::class, 'edit'])
        ->name('admin.master.savings-product-categories.edit');

    Route::put('/admin/master/kategori-simpanan/{savingsProductCategory}', [SavingsProductCategoryController::class, 'update'])
        ->name('admin.master.savings-product-categories.update');

    Route::delete('/admin/master/kategori-simpanan/{savingsProductCategory}', [SavingsProductCategoryController::class, 'destroy'])
        ->name('admin.master.savings-product-categories.destroy');
});
