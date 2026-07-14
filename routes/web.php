<?php

use App\Http\Controllers\Admin\BrandingSettingsController;
use App\Http\Controllers\Admin\BusinessUnitController;
use App\Http\Controllers\Admin\CooperativeEventController;
use App\Http\Controllers\Admin\CustomReportController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeeTypeController;
use App\Http\Controllers\Admin\FinancialReportController;
use App\Http\Controllers\Admin\FixedAssetCategoryController;
use App\Http\Controllers\Admin\FixedAssetController;
use App\Http\Controllers\Admin\FixedAssetDisposalController;
use App\Http\Controllers\Admin\FixedAssetReportController;
use App\Http\Controllers\Admin\GeneralLedgerController;
use App\Http\Controllers\Admin\InventoryReportController;
use App\Http\Controllers\Admin\JournalAdjustmentController;
use App\Http\Controllers\Admin\LoanApprovalController;
use App\Http\Controllers\Admin\LoanProductController;
use App\Http\Controllers\Admin\MemberCardController;
use App\Http\Controllers\Admin\NotificationLogController;
use App\Http\Controllers\Admin\NotificationTemplateController;
use App\Http\Controllers\Admin\OpeningBalanceController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\PurchaseReturnController;
use App\Http\Controllers\Admin\RatPackageController;
use App\Http\Controllers\Admin\SavingsProductController;
use App\Http\Controllers\Admin\SecurityAuditController;
use App\Http\Controllers\Admin\ShuController;
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\TarifParameterController;
use App\Http\Controllers\Staf\LoanApplicationController;
use App\Http\Controllers\Staf\PosController;
use App\Http\Controllers\Staf\SalesReturnController;
use App\Http\Controllers\Staf\TellerCashController;
use App\Http\Controllers\Staf\TellerController;
use App\Http\Controllers\Staf\UpfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.login'))->name('login');
});

Route::middleware(['auth', 'mfa.required'])->group(function () {
    Route::get('/home', fn () => view('home'))->name('home');

    Route::get('/admin/dashboard', [DashboardController::class, 'main'])
        ->name('admin.dashboard.index');
    Route::get('/admin/dashboard/kas-bank', [DashboardController::class, 'kasBank'])
        ->name('admin.dashboard.kas-bank');
    Route::get('/admin/dashboard/rat', [DashboardController::class, 'rat'])
        ->name('admin.dashboard.rat');
    Route::get('/admin/dashboard/kalender', [DashboardController::class, 'kalender'])
        ->name('admin.dashboard.kalender');

    Route::get('/admin/kalender/kegiatan', [CooperativeEventController::class, 'index'])
        ->name('admin.kalender.kegiatan.index');
    Route::get('/admin/kalender/kegiatan/tambah', [CooperativeEventController::class, 'create'])
        ->name('admin.kalender.kegiatan.create');
    Route::post('/admin/kalender/kegiatan', [CooperativeEventController::class, 'store'])
        ->name('admin.kalender.kegiatan.store');
    Route::post('/admin/kalender/kegiatan/{event}/batalkan', [CooperativeEventController::class, 'cancel'])
        ->name('admin.kalender.kegiatan.cancel');

    Route::get('/admin/notifikasi/template', [NotificationTemplateController::class, 'index'])
        ->name('admin.notifikasi.template.index');
    Route::get('/admin/notifikasi/template/{template}', [NotificationTemplateController::class, 'edit'])
        ->name('admin.notifikasi.template.edit');
    Route::put('/admin/notifikasi/template/{template}', [NotificationTemplateController::class, 'update'])
        ->name('admin.notifikasi.template.update');

    Route::get('/admin/notifikasi/log', [NotificationLogController::class, 'index'])
        ->name('admin.notifikasi.log.index');

    Route::get('/admin/laporan-kustom', [CustomReportController::class, 'index'])
        ->name('admin.laporan-kustom.index');
    Route::post('/admin/laporan-kustom', [CustomReportController::class, 'generate'])
        ->name('admin.laporan-kustom.generate');
    Route::get('/admin/laporan-kustom/ekspor', [CustomReportController::class, 'exports'])
        ->name('admin.laporan-kustom.exports');
    Route::get('/admin/laporan-kustom/ekspor/{export}/unduh', [CustomReportController::class, 'download'])
        ->name('admin.laporan-kustom.download');

    Route::get('/admin/tarif-parameter', [TarifParameterController::class, 'index'])
        ->name('admin.tarif-parameter.index');
    Route::put('/admin/tarif-parameter/simpanan/{product}', [TarifParameterController::class, 'updateSavings'])
        ->name('admin.tarif-parameter.savings.update');
    Route::post('/admin/tarif-parameter/simpanan/{product}/tarif', [TarifParameterController::class, 'addSavingsRate'])
        ->name('admin.tarif-parameter.savings.rate');
    Route::put('/admin/tarif-parameter/pinjaman/{product}', [TarifParameterController::class, 'updateLoan'])
        ->name('admin.tarif-parameter.loan.update');
    Route::post('/admin/tarif-parameter/pinjaman/{product}/tarif', [TarifParameterController::class, 'addLoanRate'])
        ->name('admin.tarif-parameter.loan.rate');

    Route::get('/admin/jurnal-buku-besar', [GeneralLedgerController::class, 'index'])
        ->name('admin.jurnal-buku-besar.index');

    Route::get('/admin/jurnal-penyesuaian', [JournalAdjustmentController::class, 'create'])
        ->name('admin.jurnal-penyesuaian.create');
    Route::post('/admin/jurnal-penyesuaian/{entry}', [JournalAdjustmentController::class, 'store'])
        ->name('admin.jurnal-penyesuaian.store');

    Route::get('/admin/laporan-keuangan', [FinancialReportController::class, 'index'])
        ->name('admin.laporan-keuangan.index');
    Route::post('/admin/laporan-keuangan/ekspor', [FinancialReportController::class, 'export'])
        ->name('admin.laporan-keuangan.export');
    Route::get('/admin/laporan-keuangan/ekspor', [FinancialReportController::class, 'exports'])
        ->name('admin.laporan-keuangan.exports');
    Route::get('/admin/laporan-keuangan/ekspor/{export}/unduh', [FinancialReportController::class, 'download'])
        ->name('admin.laporan-keuangan.download');

    Route::get('/admin/shu', [ShuController::class, 'index'])
        ->name('admin.shu.index');
    Route::post('/admin/shu/kategori', [ShuController::class, 'storeCategory'])
        ->name('admin.shu.kategori.store');

    Route::get('/admin/rat/paket', [RatPackageController::class, 'download'])
        ->name('admin.rat.paket.download');

    Route::get('/admin/keamanan-audit', [SecurityAuditController::class, 'index'])
        ->name('admin.keamanan-audit.index');
    Route::get('/admin/keamanan-audit/log', [SecurityAuditController::class, 'auditLog'])
        ->name('admin.keamanan-audit.log');
    Route::get('/admin/keamanan-audit/consent', [SecurityAuditController::class, 'consentLog'])
        ->name('admin.keamanan-audit.consent');
    Route::get('/admin/keamanan-audit/ekspor', [SecurityAuditController::class, 'export'])
        ->name('admin.keamanan-audit.export');

    Route::get('/admin/pengaturan/branding', [BrandingSettingsController::class, 'edit'])
        ->name('admin.branding.edit');
    Route::post('/admin/pengaturan/branding', [BrandingSettingsController::class, 'update'])
        ->name('admin.branding.update');

    Route::get('/admin/anggota/{member}/kartu', [MemberCardController::class, 'show'])
        ->name('admin.members.card');
    Route::get('/admin/anggota/{member}/kartu/pdf', [MemberCardController::class, 'downloadPdf'])
        ->name('admin.members.card.pdf');

    Route::get('/admin/master/produk-simpanan', [SavingsProductController::class, 'index'])
        ->name('admin.master.savings-products.index');
    Route::get('/admin/master/produk-simpanan/tambah', [SavingsProductController::class, 'create'])
        ->name('admin.master.savings-products.create');
    Route::post('/admin/master/produk-simpanan', [SavingsProductController::class, 'store'])
        ->name('admin.master.savings-products.store');

    Route::get('/admin/master/produk-pinjaman', [LoanProductController::class, 'index'])
        ->name('admin.master.loan-products.index');
    Route::get('/admin/master/produk-pinjaman/tambah', [LoanProductController::class, 'create'])
        ->name('admin.master.loan-products.create');
    Route::post('/admin/master/produk-pinjaman', [LoanProductController::class, 'store'])
        ->name('admin.master.loan-products.store');

    Route::get('/staf/teller', [TellerController::class, 'create'])
        ->name('staf.teller.create');
    Route::post('/staf/teller/preview', [TellerController::class, 'preview'])
        ->name('staf.teller.preview');
    Route::post('/staf/teller', [TellerController::class, 'store'])
        ->name('staf.teller.store');

    Route::get('/staf/kas', [TellerCashController::class, 'create'])
        ->name('staf.kas.create');
    Route::post('/staf/kas/preview', [TellerCashController::class, 'preview'])
        ->middleware('branch.scope')
        ->name('staf.kas.preview');
    Route::post('/staf/kas', [TellerCashController::class, 'store'])
        ->middleware('branch.scope')
        ->name('staf.kas.store');

    Route::get('/staf/pengajuan-pinjaman', [LoanApplicationController::class, 'create'])
        ->name('staf.pengajuan-pinjaman.create');
    Route::post('/staf/pengajuan-pinjaman/simulasi', [LoanApplicationController::class, 'simulate'])
        ->name('staf.pengajuan-pinjaman.simulate');
    Route::post('/staf/pengajuan-pinjaman', [LoanApplicationController::class, 'store'])
        ->name('staf.pengajuan-pinjaman.store');

    Route::get('/admin/pinjaman', [LoanApprovalController::class, 'index'])
        ->name('admin.pinjaman.index');
    Route::post('/admin/pinjaman/{loan}/keputusan', [LoanApprovalController::class, 'decide'])
        ->name('admin.pinjaman.decide');

    Route::get('/admin/master/jenis-iuran', [FeeTypeController::class, 'index'])
        ->name('admin.master.fee-types.index');
    Route::get('/admin/master/jenis-iuran/tambah', [FeeTypeController::class, 'create'])
        ->name('admin.master.fee-types.create');
    Route::post('/admin/master/jenis-iuran', [FeeTypeController::class, 'store'])
        ->name('admin.master.fee-types.store');

    Route::get('/staf/upf', [UpfController::class, 'index'])
        ->name('staf.upf.index');
    Route::post('/staf/upf/tagihan', [UpfController::class, 'generateBilling'])
        ->name('staf.upf.tagihan');
    Route::post('/staf/upf/pembayaran', [UpfController::class, 'recordPayment'])
        ->name('staf.upf.pembayaran');

    Route::get('/admin/unit-usaha', [BusinessUnitController::class, 'index'])
        ->name('admin.unit-usaha.index');
    Route::post('/admin/unit-usaha', [BusinessUnitController::class, 'store'])
        ->name('admin.unit-usaha.store');
    Route::post('/admin/unit-usaha/transaksi', [BusinessUnitController::class, 'recordTransaction'])
        ->name('admin.unit-usaha.transaksi');

    Route::get('/admin/saldo-awal', [OpeningBalanceController::class, 'index'])
        ->name('admin.saldo-awal.index');
    Route::get('/admin/saldo-awal/tambah', [OpeningBalanceController::class, 'create'])
        ->name('admin.saldo-awal.create');
    Route::post('/admin/saldo-awal', [OpeningBalanceController::class, 'store'])
        ->name('admin.saldo-awal.store');
    Route::get('/admin/saldo-awal/{batch}', [OpeningBalanceController::class, 'show'])
        ->name('admin.saldo-awal.show');
    Route::post('/admin/saldo-awal/{batch}/import/{subModule}', [OpeningBalanceController::class, 'import'])
        ->name('admin.saldo-awal.import');
    Route::post('/admin/saldo-awal/{batch}/lock', [OpeningBalanceController::class, 'lock'])
        ->name('admin.saldo-awal.lock');

    Route::get('/admin/master/barang', [ProductController::class, 'index'])
        ->name('admin.master.products.index');
    Route::get('/admin/master/barang/tambah', [ProductController::class, 'create'])
        ->name('admin.master.products.create');
    Route::post('/admin/master/barang', [ProductController::class, 'store'])
        ->name('admin.master.products.store');

    Route::get('/admin/master/supplier', [SupplierController::class, 'index'])
        ->name('admin.master.suppliers.index');
    Route::get('/admin/master/supplier/tambah', [SupplierController::class, 'create'])
        ->name('admin.master.suppliers.create');
    Route::post('/admin/master/supplier', [SupplierController::class, 'store'])
        ->name('admin.master.suppliers.store');

    Route::get('/admin/pembelian', [PurchaseController::class, 'index'])
        ->name('admin.pembelian.index');
    Route::get('/admin/pembelian/tambah', [PurchaseController::class, 'create'])
        ->name('admin.pembelian.create');
    Route::post('/admin/pembelian', [PurchaseController::class, 'store'])
        ->middleware('branch.scope')
        ->name('admin.pembelian.store');
    Route::post('/admin/pembelian/{purchase}/keputusan', [PurchaseController::class, 'decide'])
        ->name('admin.pembelian.decide');
    Route::post('/admin/pembelian/{purchase}/bayar', [PurchaseController::class, 'pay'])
        ->name('admin.pembelian.pay');

    Route::get('/staf/pos', [PosController::class, 'create'])
        ->name('staf.pos.create');
    Route::post('/staf/pos', [PosController::class, 'store'])
        ->middleware('branch.scope')
        ->name('staf.pos.store');
    Route::get('/staf/pos/{sale}/struk', [PosController::class, 'receipt'])
        ->name('staf.pos.receipt');

    Route::get('/admin/pembelian/retur', [PurchaseReturnController::class, 'create'])
        ->name('admin.pembelian.retur.create');
    Route::post('/admin/pembelian/retur', [PurchaseReturnController::class, 'store'])
        ->name('admin.pembelian.retur.store');

    Route::get('/staf/pos/retur', [SalesReturnController::class, 'create'])
        ->name('staf.pos.retur.create');
    Route::post('/staf/pos/retur', [SalesReturnController::class, 'store'])
        ->name('staf.pos.retur.store');

    Route::get('/admin/persediaan/koreksi', [StockAdjustmentController::class, 'index'])
        ->name('admin.persediaan.koreksi.index');
    Route::get('/admin/persediaan/koreksi/tambah', [StockAdjustmentController::class, 'create'])
        ->name('admin.persediaan.koreksi.create');
    Route::post('/admin/persediaan/koreksi', [StockAdjustmentController::class, 'store'])
        ->middleware('branch.scope')
        ->name('admin.persediaan.koreksi.store');
    Route::post('/admin/persediaan/koreksi/{adjustment}/keputusan', [StockAdjustmentController::class, 'decide'])
        ->name('admin.persediaan.koreksi.decide');

    Route::get('/admin/persediaan/laporan/saldo', [InventoryReportController::class, 'saldo'])
        ->name('admin.persediaan.laporan.saldo');
    Route::get('/admin/persediaan/laporan/kartu', [InventoryReportController::class, 'kartu'])
        ->name('admin.persediaan.laporan.kartu');

    Route::get('/admin/master/kategori-aktiva-tetap', [FixedAssetCategoryController::class, 'index'])
        ->name('admin.master.fixed-asset-categories.index');
    Route::get('/admin/master/kategori-aktiva-tetap/tambah', [FixedAssetCategoryController::class, 'create'])
        ->name('admin.master.fixed-asset-categories.create');
    Route::post('/admin/master/kategori-aktiva-tetap', [FixedAssetCategoryController::class, 'store'])
        ->name('admin.master.fixed-asset-categories.store');

    Route::get('/admin/aktiva-tetap', [FixedAssetController::class, 'index'])
        ->name('admin.aktiva-tetap.index');
    Route::get('/admin/aktiva-tetap/tambah', [FixedAssetController::class, 'create'])
        ->name('admin.aktiva-tetap.create');
    Route::post('/admin/aktiva-tetap', [FixedAssetController::class, 'store'])
        ->middleware('branch.scope')
        ->name('admin.aktiva-tetap.store');
    Route::post('/admin/aktiva-tetap/{asset}/keputusan', [FixedAssetController::class, 'decide'])
        ->name('admin.aktiva-tetap.decide');

    Route::get('/admin/aktiva-tetap/pelepasan', [FixedAssetDisposalController::class, 'create'])
        ->name('admin.aktiva-tetap.pelepasan.create');
    Route::post('/admin/aktiva-tetap/pelepasan', [FixedAssetDisposalController::class, 'store'])
        ->name('admin.aktiva-tetap.pelepasan.store');

    Route::get('/admin/aktiva-tetap/laporan/daftar', [FixedAssetReportController::class, 'daftar'])
        ->name('admin.aktiva-tetap.laporan.daftar');
    Route::get('/admin/aktiva-tetap/laporan/kartu-penyusutan', [FixedAssetReportController::class, 'kartuPenyusutan'])
        ->name('admin.aktiva-tetap.laporan.kartu-penyusutan');
    Route::get('/admin/aktiva-tetap/laporan/ringkasan-penyusutan', [FixedAssetReportController::class, 'ringkasanPenyusutan'])
        ->name('admin.aktiva-tetap.laporan.ringkasan-penyusutan');
    Route::get('/admin/aktiva-tetap/laporan/pelepasan', [FixedAssetReportController::class, 'pelepasan'])
        ->name('admin.aktiva-tetap.laporan.pelepasan');
});
