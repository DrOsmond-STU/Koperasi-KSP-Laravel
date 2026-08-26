<?php

use App\Http\Controllers\Admin\BrandingSettingsController;
use App\Http\Controllers\Anggota\LoanApplicationController as MemberLoanApplicationController;
use App\Http\Controllers\Anggota\LoanController as MemberLoanController;
use App\Http\Controllers\Anggota\LoanRepaymentController;
use App\Http\Controllers\Staf\LoanRepaymentController as StafLoanRepaymentController;
use App\Http\Controllers\Anggota\PortalController;
use App\Http\Controllers\Anggota\PrintLoanController as MemberPrintLoanController;
use App\Http\Controllers\Anggota\PrintSavingsController as MemberPrintSavingsController;
use App\Http\Controllers\Anggota\SavingsController as MemberSavingsController;
use App\Http\Controllers\Anggota\SavingsDepositController;
use App\Http\Controllers\Anggota\WithdrawalRequestController;
use App\Http\Controllers\Admin\BusinessUnitController;
use App\Http\Controllers\Admin\CooperativeEventController;
use App\Http\Controllers\Admin\CustomReportController;
use App\Http\Controllers\Admin\LaporanController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinancialReportController;
use App\Http\Controllers\Admin\FixedAssetBarcodeController;
use App\Http\Controllers\Admin\FixedAssetCategoryController;
use App\Http\Controllers\Admin\FixedAssetController;
use App\Http\Controllers\Admin\FixedAssetDisposalController;
use App\Http\Controllers\Admin\FixedAssetReportController;
use App\Http\Controllers\Admin\GeneralJournalController;
use App\Http\Controllers\Admin\GeneralLedgerController;
use App\Http\Controllers\Admin\InventoryReportController;
use App\Http\Controllers\Admin\JournalAdjustmentController;
use App\Http\Controllers\Admin\LoanApprovalController;
use App\Http\Controllers\Admin\LoanProductController;
use App\Http\Controllers\Admin\LoanTenorRateImportController;
use App\Http\Controllers\Admin\MemberCardController;
use App\Http\Controllers\Admin\MemberCardTemplateController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\MemberTypeController;
use App\Http\Controllers\Admin\NotificationLogController;
use App\Http\Controllers\Admin\NotificationTemplateController;
use App\Http\Controllers\Admin\OpeningBalanceController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\PrintLoanApplicationController;
use App\Http\Controllers\Admin\PrintLoanController;
use App\Http\Controllers\Admin\PrintLoanRepaymentController;
use App\Http\Controllers\Admin\PrintSavingsController;
use App\Http\Controllers\Admin\PrintWithdrawalRequestController;
use App\Http\Controllers\Admin\PrintSettingsController;
use App\Http\Controllers\Admin\ProductBarcodeController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\PurchaseReturnController;
use App\Http\Controllers\Admin\BranchCashSettingsController;
use App\Http\Controllers\Admin\RatPackageController;
use App\Http\Controllers\Admin\ChartOfAccountController;
use App\Http\Controllers\Admin\RetributionTypeController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SignatureConfigController;
use App\Http\Controllers\Staf\RetributionController;
use App\Http\Controllers\Admin\SavingsProductController;
use App\Http\Controllers\Admin\SecurityAuditController;
use App\Http\Controllers\Admin\ShuController;
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\TarifParameterController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Staf\LoanApplicationController;
use App\Http\Controllers\Staf\PosController;
use App\Http\Controllers\Staf\SalesReturnController;
use App\Http\Controllers\Staf\TellerCashController;
use App\Http\Controllers\Staf\TellerController;
use Illuminate\Support\Facades\Route;

// Wizard setup web (lihat app/Http/Controllers/InstallController.php +
// PANDUAN_INSTALASI_SHARED_HOSTING.md) — dijaga middleware 'not.installed'
// supaya tidak bisa diakses ulang setelah storage/installed ada.
// BootstrapInstallerEnvironment (bootstrap/app.php, di depan grup 'web')
// otomatis mengarahkan semua request lain ke sini selama belum terinstal.
Route::prefix('install')->name('install.')->middleware('not.installed')->group(function () {
    Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
    Route::get('/database', [InstallController::class, 'showDatabase'])->name('database');
    Route::post('/database', [InstallController::class, 'saveDatabase'])->name('database.store');
    Route::get('/migrate', [InstallController::class, 'showMigrate'])->name('migrate');
    Route::post('/migrate', [InstallController::class, 'runMigrate'])->name('migrate.run');
    Route::get('/admin', [InstallController::class, 'showAdmin'])->name('admin');
    Route::post('/admin', [InstallController::class, 'saveAdmin'])->name('admin.store');
    Route::get('/finish', [InstallController::class, 'finish'])->name('finish');
});

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.login'))->name('login');
});

Route::middleware(['auth', 'active.user', 'mfa.required'])->group(function () {
    Route::get('/home', fn () => view('home'))->name('home');

    Route::get('/profil-saya', [ProfileController::class, 'edit'])->name('profile.edit');

    Route::get('/admin/dashboard', [DashboardController::class, 'main'])
        ->name('admin.dashboard.index');
    Route::get('/admin/dashboard/kas-bank', [DashboardController::class, 'kasBank'])
        ->name('admin.dashboard.kas-bank');
    Route::get('/admin/dashboard/kas-bank/cetak', [DashboardController::class, 'printKasBankHarian'])
        ->name('admin.dashboard.kas-bank.print');
    Route::get('/admin/dashboard/rat', [DashboardController::class, 'rat'])
        ->name('admin.dashboard.rat');
    Route::get('/admin/dashboard/kalender', [DashboardController::class, 'kalender'])
        ->name('admin.dashboard.kalender');

    Route::get('/admin/kalender/kegiatan', [CooperativeEventController::class, 'index'])
        ->name('admin.kalender.kegiatan.index');
    Route::get('/admin/kalender/kegiatan/export-pdf', [CooperativeEventController::class, 'exportPdf'])
        ->name('admin.kalender.kegiatan.export-pdf');
    Route::get('/admin/kalender/kegiatan/export-excel', [CooperativeEventController::class, 'exportExcel'])
        ->name('admin.kalender.kegiatan.export-excel');
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

    Route::get('/admin/laporan', [LaporanController::class, 'index'])
        ->name('admin.laporan.index');
    Route::get('/admin/laporan/{module}/export-pdf', [LaporanController::class, 'exportPdf'])
        ->name('admin.laporan.export-pdf');
    Route::get('/admin/laporan/{module}/export-excel', [LaporanController::class, 'exportExcel'])
        ->name('admin.laporan.export-excel');
    // Route khusus untuk cetak Saldo Awal Neraca dalam bentuk skontro.
    // Path literal (bukan {module}) sengaja diletakkan sebelum route
    // `/admin/laporan/{module}` supaya tidak jatuh ke pola catch-all yang
    // ada di bawahnya.
    Route::get('/admin/laporan/saldo-awal-neraca/print-scontro', [LaporanController::class, 'printSaldoAwalNeracaSkontro'])
        ->name('admin.laporan.saldo-awal-neraca.print-scontro');
    Route::get('/admin/laporan/{module}', [LaporanController::class, 'show'])
        ->name('admin.laporan.show');

    Route::get('/admin/tarif-parameter', [TarifParameterController::class, 'index'])
        ->name('admin.tarif-parameter.index');
    Route::put('/admin/tarif-parameter/simpanan/{product}', [TarifParameterController::class, 'updateSavings'])
        ->name('admin.tarif-parameter.savings.update');
    Route::post('/admin/tarif-parameter/simpanan/{product}/tarif', [TarifParameterController::class, 'addSavingsRate'])
        ->name('admin.tarif-parameter.savings.rate');
    Route::put('/admin/tarif-parameter/simpanan/{product}/tarif/{rate}', [TarifParameterController::class, 'updateSavingsRate'])
        ->name('admin.tarif-parameter.savings.rate.update');
    Route::put('/admin/tarif-parameter/pinjaman/{product}', [TarifParameterController::class, 'updateLoan'])
        ->name('admin.tarif-parameter.loan.update');
    Route::post('/admin/tarif-parameter/pinjaman/{product}/tarif', [TarifParameterController::class, 'addLoanRate'])
        ->name('admin.tarif-parameter.loan.rate');
    Route::put('/admin/tarif-parameter/pinjaman/{product}/tarif/{rate}', [TarifParameterController::class, 'updateLoanRate'])
        ->name('admin.tarif-parameter.loan.rate.update');

    Route::get('/admin/jurnal-buku-besar', [GeneralLedgerController::class, 'index'])
        ->name('admin.jurnal-buku-besar.index');

    Route::get('/admin/jurnal-penyesuaian', [JournalAdjustmentController::class, 'create'])
        ->name('admin.jurnal-penyesuaian.create');
    Route::post('/admin/jurnal-penyesuaian/{entry}', [JournalAdjustmentController::class, 'store'])
        ->name('admin.jurnal-penyesuaian.store');

    Route::get('/admin/jurnal-umum', [GeneralJournalController::class, 'create'])
        ->name('admin.jurnal-umum.create');
    Route::post('/admin/jurnal-umum', [GeneralJournalController::class, 'store'])
        ->name('admin.jurnal-umum.store');
    Route::get('/admin/jurnal-umum/{entry}/cetak', [GeneralJournalController::class, 'print'])
        ->name('admin.jurnal-umum.print');

    Route::get('/admin/laporan-keuangan', [FinancialReportController::class, 'index'])
        ->name('admin.laporan-keuangan.index');
    Route::post('/admin/laporan-keuangan/ekspor', [FinancialReportController::class, 'export'])
        ->name('admin.laporan-keuangan.export');
    Route::get('/admin/laporan-keuangan/ekspor', [FinancialReportController::class, 'exports'])
        ->name('admin.laporan-keuangan.exports');
    Route::get('/admin/laporan-keuangan/ekspor/{export}/unduh', [FinancialReportController::class, 'download'])
        ->name('admin.laporan-keuangan.download');
    Route::get('/admin/laporan-keuangan/neraca-scontro/cetak', [FinancialReportController::class, 'printScontro'])
        ->name('admin.laporan-keuangan.print-scontro');

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

    Route::get('/admin/pengguna', [UserController::class, 'index'])
        ->name('admin.users.index');
    Route::get('/admin/pengguna/tambah', [UserController::class, 'create'])
        ->name('admin.users.create');
    Route::post('/admin/pengguna', [UserController::class, 'store'])
        ->name('admin.users.store');
    Route::get('/admin/pengguna/{user}/edit', [UserController::class, 'edit'])
        ->name('admin.users.edit');
    Route::put('/admin/pengguna/{user}', [UserController::class, 'update'])
        ->name('admin.users.update');
    Route::post('/admin/pengguna/{user}/nonaktifkan', [UserController::class, 'deactivate'])
        ->name('admin.users.deactivate');
    Route::post('/admin/pengguna/{user}/aktifkan', [UserController::class, 'reactivate'])
        ->name('admin.users.reactivate');

    Route::get('/admin/role-permission', [RolePermissionController::class, 'index'])
        ->name('admin.role-permission.index');
    Route::get('/admin/role-permission/{role}/edit', [RolePermissionController::class, 'edit'])
        ->name('admin.role-permission.edit');
    Route::put('/admin/role-permission/{role}', [RolePermissionController::class, 'update'])
        ->name('admin.role-permission.update');

    Route::get('/admin/pengaturan/branding', [BrandingSettingsController::class, 'edit'])
        ->name('admin.branding.edit');
    Route::post('/admin/pengaturan/branding', [BrandingSettingsController::class, 'update'])
        ->name('admin.branding.update');

    Route::get('/admin/pengaturan/cetakan', [PrintSettingsController::class, 'edit'])
        ->name('admin.pengaturan.cetakan.edit');
    Route::put('/admin/pengaturan/cetakan', [PrintSettingsController::class, 'update'])
        ->name('admin.pengaturan.cetakan.update');

    Route::get('/admin/pengaturan/tanda-tangan', [SignatureConfigController::class, 'index'])
        ->name('admin.pengaturan.tanda-tangan.index');
    Route::post('/admin/pengaturan/tanda-tangan/penanda', [SignatureConfigController::class, 'storeSignatory'])
        ->name('admin.pengaturan.tanda-tangan.signatory.store');
    Route::put('/admin/pengaturan/tanda-tangan/penanda/{signatory}', [SignatureConfigController::class, 'updateSignatory'])
        ->name('admin.pengaturan.tanda-tangan.signatory.update');
    Route::delete('/admin/pengaturan/tanda-tangan/penanda/{signatory}', [SignatureConfigController::class, 'destroySignatory'])
        ->name('admin.pengaturan.tanda-tangan.signatory.destroy');
    Route::post('/admin/pengaturan/tanda-tangan/slot', [SignatureConfigController::class, 'storeSlot'])
        ->name('admin.pengaturan.tanda-tangan.slot.store');
    Route::put('/admin/pengaturan/tanda-tangan/slot/{slot}', [SignatureConfigController::class, 'updateSlot'])
        ->name('admin.pengaturan.tanda-tangan.slot.update');
    Route::delete('/admin/pengaturan/tanda-tangan/slot/{slot}', [SignatureConfigController::class, 'destroySlot'])
        ->name('admin.pengaturan.tanda-tangan.slot.destroy');

    Route::get('/admin/pengaturan/kas-cabang', [BranchCashSettingsController::class, 'index'])
        ->name('admin.pengaturan.kas-cabang.index');
    Route::put('/admin/pengaturan/kas-cabang/{branch}', [BranchCashSettingsController::class, 'update'])
        ->name('admin.pengaturan.kas-cabang.update');

    Route::get('/admin/master/jenis-anggota', [MemberTypeController::class, 'index'])
        ->name('admin.master.member-types.index');
    Route::get('/admin/master/jenis-anggota/tambah', [MemberTypeController::class, 'create'])
        ->name('admin.master.member-types.create');
    Route::post('/admin/master/jenis-anggota', [MemberTypeController::class, 'store'])
        ->name('admin.master.member-types.store');
    Route::get('/admin/master/jenis-anggota/{memberType}/edit', [MemberTypeController::class, 'edit'])
        ->name('admin.master.member-types.edit');
    Route::put('/admin/master/jenis-anggota/{memberType}', [MemberTypeController::class, 'update'])
        ->name('admin.master.member-types.update');
    Route::delete('/admin/master/jenis-anggota/{memberType}', [MemberTypeController::class, 'destroy'])
        ->name('admin.master.member-types.destroy');

    Route::get('/admin/anggota', [MemberController::class, 'index'])
        ->name('admin.members.index');
    Route::get('/admin/anggota/cetak', [MemberController::class, 'printList'])
        ->name('admin.members.print');
    Route::get('/admin/anggota/tambah', [MemberController::class, 'create'])
        ->name('admin.members.create');
    Route::post('/admin/anggota', [MemberController::class, 'store'])
        ->name('admin.members.store');
    Route::get('/admin/anggota/{member}/edit', [MemberController::class, 'edit'])
        ->name('admin.members.edit');
    Route::put('/admin/anggota/{member}', [MemberController::class, 'update'])
        ->name('admin.members.update');

    Route::get('/admin/anggota/{member}/kartu', [MemberCardController::class, 'show'])
        ->name('admin.members.card');
    Route::get('/admin/anggota/{member}/kartu/pdf', [MemberCardController::class, 'downloadPdf'])
        ->name('admin.members.card.pdf');
    Route::get('/admin/anggota/kartu/cetak-massal', [MemberCardController::class, 'printBulk'])
        ->name('admin.members.card.print-bulk');

    Route::get('/admin/master/kartu-anggota', [MemberCardTemplateController::class, 'index'])
        ->name('admin.master.member-card-templates.index');
    Route::get('/admin/master/kartu-anggota/tambah', [MemberCardTemplateController::class, 'create'])
        ->name('admin.master.member-card-templates.create');
    Route::post('/admin/master/kartu-anggota', [MemberCardTemplateController::class, 'store'])
        ->name('admin.master.member-card-templates.store');
    Route::get('/admin/master/kartu-anggota/{memberCardTemplate}/edit', [MemberCardTemplateController::class, 'edit'])
        ->name('admin.master.member-card-templates.edit');
    Route::put('/admin/master/kartu-anggota/{memberCardTemplate}', [MemberCardTemplateController::class, 'update'])
        ->name('admin.master.member-card-templates.update');
    Route::post('/admin/master/kartu-anggota/{memberCardTemplate}/aktifkan', [MemberCardTemplateController::class, 'activate'])
        ->name('admin.master.member-card-templates.activate');

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
    Route::get('/admin/master/produk-pinjaman/{loanProduct}/edit', [LoanProductController::class, 'edit'])
        ->name('admin.master.loan-products.edit');
    Route::put('/admin/master/produk-pinjaman/{loanProduct}', [LoanProductController::class, 'update'])
        ->name('admin.master.loan-products.update');

    Route::get('/staf/teller', [TellerController::class, 'create'])
        ->name('staf.teller.create');
    Route::get('/staf/teller/buka-rekening', [TellerController::class, 'createAccount'])
        ->name('staf.teller.buka-rekening.create');
    Route::post('/staf/teller/buka-rekening', [TellerController::class, 'storeAccount'])
        ->name('staf.teller.buka-rekening.store');
    Route::post('/staf/teller/preview', [TellerController::class, 'preview'])
        ->name('staf.teller.preview');
    Route::post('/staf/teller', [TellerController::class, 'store'])
        ->name('staf.teller.store');
    Route::post('/staf/teller/{transaction}/batalkan', [TellerController::class, 'cancel'])
        ->name('staf.teller.cancel');
    Route::post('/staf/teller/penarikan/{withdrawalRequest}/keputusan', [TellerController::class, 'decideWithdrawal'])
        ->name('staf.teller.decide-withdrawal');
    Route::get('/staf/teller/{transaction}/cetak', [TellerController::class, 'printReceipt'])
        ->name('staf.teller.print-receipt');

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

    Route::get('/staf/angsuran', [StafLoanRepaymentController::class, 'create'])
        ->name('staf.angsuran.create');
    Route::post('/staf/angsuran/preview', [StafLoanRepaymentController::class, 'preview'])
        ->name('staf.angsuran.preview');
    Route::post('/staf/angsuran', [StafLoanRepaymentController::class, 'store'])
        ->name('staf.angsuran.store');

    Route::get('/admin/pinjaman', [LoanApprovalController::class, 'index'])
        ->name('admin.pinjaman.index');
    Route::post('/admin/pinjaman/{loan}/keputusan', [LoanApprovalController::class, 'decide'])
        ->name('admin.pinjaman.decide');
    Route::post('/admin/pinjaman/{loan}/batalkan', [LoanApprovalController::class, 'cancel'])
        ->name('admin.pinjaman.cancel');

    Route::get('/admin/pinjaman/impor-tenor-tarif', [LoanTenorRateImportController::class, 'create'])
        ->name('admin.pinjaman.import-tenor-tarif.create');
    Route::post('/admin/pinjaman/impor-tenor-tarif', [LoanTenorRateImportController::class, 'store'])
        ->name('admin.pinjaman.import-tenor-tarif.store');

    Route::get('/admin/cetakan/simpanan', [PrintSavingsController::class, 'index'])
        ->name('admin.print.savings.index');
    Route::get('/admin/cetakan/pinjaman', [PrintLoanController::class, 'index'])
        ->name('admin.print.loans.index');
    Route::get('/admin/cetakan/pinjaman/{loan}/angsuran', [PrintLoanController::class, 'schedule'])
        ->name('admin.print.loans.schedule');
    Route::get('/admin/cetakan/pengajuan-pinjaman/{loan}', [PrintLoanApplicationController::class, 'show'])
        ->name('admin.print.loan-application.show');
    Route::get('/admin/cetakan/pengajuan-penarikan/{withdrawalRequest}', [PrintWithdrawalRequestController::class, 'show'])
        ->name('admin.print.withdrawal-request.show');
    Route::get('/admin/cetakan/angsuran/{repayment}', [PrintLoanRepaymentController::class, 'show'])
        ->name('admin.print.loan-repayment.show');

    Route::get('/admin/master/bagan-akun', [ChartOfAccountController::class, 'index'])
        ->name('admin.master.chart-of-accounts.index');
    Route::get('/admin/master/bagan-akun/export-pdf', [ChartOfAccountController::class, 'exportPdf'])
        ->name('admin.master.chart-of-accounts.export-pdf');
    Route::get('/admin/master/bagan-akun/export-excel', [ChartOfAccountController::class, 'exportExcel'])
        ->name('admin.master.chart-of-accounts.export-excel');
    Route::get('/admin/master/bagan-akun/tambah', [ChartOfAccountController::class, 'create'])
        ->name('admin.master.chart-of-accounts.create');
    Route::post('/admin/master/bagan-akun', [ChartOfAccountController::class, 'store'])
        ->name('admin.master.chart-of-accounts.store');
    Route::get('/admin/master/bagan-akun/{chartOfAccount}/edit', [ChartOfAccountController::class, 'edit'])
        ->name('admin.master.chart-of-accounts.edit');
    Route::put('/admin/master/bagan-akun/{chartOfAccount}', [ChartOfAccountController::class, 'update'])
        ->name('admin.master.chart-of-accounts.update');
    Route::delete('/admin/master/bagan-akun/{chartOfAccount}', [ChartOfAccountController::class, 'destroy'])
        ->name('admin.master.chart-of-accounts.destroy');

    Route::get('/admin/master/jenis-retribusi', [RetributionTypeController::class, 'index'])
        ->name('admin.master.retribution-types.index');
    Route::get('/admin/master/jenis-retribusi/tambah', [RetributionTypeController::class, 'create'])
        ->name('admin.master.retribution-types.create');
    Route::post('/admin/master/jenis-retribusi', [RetributionTypeController::class, 'store'])
        ->name('admin.master.retribution-types.store');
    Route::get('/admin/master/jenis-retribusi/{retributionType}/edit', [RetributionTypeController::class, 'edit'])
        ->name('admin.master.retribution-types.edit');
    Route::put('/admin/master/jenis-retribusi/{retributionType}', [RetributionTypeController::class, 'update'])
        ->name('admin.master.retribution-types.update');

    Route::get('/staf/retribusi-upf', [RetributionController::class, 'index'])
        ->name('staf.retribusi-upf.index');
    Route::post('/staf/retribusi-upf', [RetributionController::class, 'store'])
        ->middleware('branch.scope')
        ->name('staf.retribusi-upf.store');
    Route::post('/staf/retribusi-upf/laporan', [RetributionController::class, 'generateLaporan'])
        ->name('staf.retribusi-upf.laporan');
    Route::get('/staf/retribusi-upf/cetak-harian', [RetributionController::class, 'printHarian'])
        ->name('staf.retribusi-upf.print-harian');
    Route::get('/staf/retribusi-upf/cetak-rekap', [RetributionController::class, 'printRekap'])
        ->name('staf.retribusi-upf.print-rekap');
    Route::post('/staf/retribusi-upf/{transaction}/batalkan', [RetributionController::class, 'cancel'])
        ->name('staf.retribusi-upf.cancel');

    Route::get('/admin/unit-usaha', [BusinessUnitController::class, 'index'])
        ->name('admin.unit-usaha.index');
    Route::post('/admin/unit-usaha', [BusinessUnitController::class, 'store'])
        ->name('admin.unit-usaha.store');
    Route::post('/admin/unit-usaha/transaksi', [BusinessUnitController::class, 'recordTransaction'])
        ->name('admin.unit-usaha.transaksi');
    Route::get('/admin/unit-usaha/{unit}/cetak', [BusinessUnitController::class, 'print'])
        ->name('admin.unit-usaha.print');

    Route::get('/admin/saldo-awal', [OpeningBalanceController::class, 'index'])
        ->name('admin.saldo-awal.index');
    Route::get('/admin/saldo-awal/tambah', [OpeningBalanceController::class, 'create'])
        ->name('admin.saldo-awal.create');
    Route::post('/admin/saldo-awal', [OpeningBalanceController::class, 'store'])
        ->name('admin.saldo-awal.store');
    Route::get('/admin/saldo-awal/template/{subModule}', [OpeningBalanceController::class, 'downloadTemplate'])
        ->name('admin.saldo-awal.template');
    Route::get('/admin/saldo-awal/{batch}', [OpeningBalanceController::class, 'show'])
        ->name('admin.saldo-awal.show');
    Route::post('/admin/saldo-awal/{batch}/import/{subModule}', [OpeningBalanceController::class, 'import'])
        ->name('admin.saldo-awal.import');
    Route::post('/admin/saldo-awal/{batch}/baris/simpanan', [OpeningBalanceController::class, 'storeSavingRow'])
        ->name('admin.saldo-awal.row.savings.store');
    Route::post('/admin/saldo-awal/{batch}/baris/pinjaman', [OpeningBalanceController::class, 'storeLoanRow'])
        ->name('admin.saldo-awal.row.loans.store');
    Route::post('/admin/saldo-awal/{batch}/baris/angsuran', [OpeningBalanceController::class, 'storeInstallmentRow'])
        ->name('admin.saldo-awal.row.installments.store');
    Route::post('/admin/saldo-awal/{batch}/baris/coa', [OpeningBalanceController::class, 'storeCoaRow'])
        ->name('admin.saldo-awal.row.coa.store');
    Route::post('/admin/saldo-awal/{batch}/baris/upf', [OpeningBalanceController::class, 'storeUpfRow'])
        ->name('admin.saldo-awal.row.upf.store');
    Route::post('/admin/saldo-awal/{batch}/baris/persediaan', [OpeningBalanceController::class, 'storeStockRow'])
        ->name('admin.saldo-awal.row.stock.store');
    Route::delete('/admin/saldo-awal/{batch}/baris/{subModule}/{rowId}', [OpeningBalanceController::class, 'destroyRow'])
        ->name('admin.saldo-awal.row.destroy');
    Route::post('/admin/saldo-awal/{batch}/lock', [OpeningBalanceController::class, 'lock'])
        ->name('admin.saldo-awal.lock');

    Route::get('/admin/master/barang', [ProductController::class, 'index'])
        ->name('admin.master.products.index');
    Route::get('/admin/master/barang/export-pdf', [ProductController::class, 'exportPdf'])
        ->name('admin.master.products.export-pdf');
    Route::get('/admin/master/barang/export-excel', [ProductController::class, 'exportExcel'])
        ->name('admin.master.products.export-excel');
    Route::get('/admin/master/barang/tambah', [ProductController::class, 'create'])
        ->name('admin.master.products.create');
    Route::post('/admin/master/barang', [ProductController::class, 'store'])
        ->name('admin.master.products.store');
    Route::get('/admin/master/barang/{product}/edit', [ProductController::class, 'edit'])
        ->name('admin.master.products.edit');
    Route::put('/admin/master/barang/{product}', [ProductController::class, 'update'])
        ->name('admin.master.products.update');
    Route::get('/admin/master/barang/cetak-barcode', [ProductBarcodeController::class, 'print'])
        ->name('admin.master.products.print-barcode');

    Route::get('/admin/master/supplier', [SupplierController::class, 'index'])
        ->name('admin.master.suppliers.index');
    Route::get('/admin/master/supplier/export-pdf', [SupplierController::class, 'exportPdf'])
        ->name('admin.master.suppliers.export-pdf');
    Route::get('/admin/master/supplier/export-excel', [SupplierController::class, 'exportExcel'])
        ->name('admin.master.suppliers.export-excel');
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
    Route::get('/admin/pembelian/{purchase}/cetak', [PurchaseController::class, 'print'])
        ->name('admin.pembelian.print');

    Route::get('/staf/pos', [PosController::class, 'create'])
        ->name('staf.pos.create');
    Route::post('/staf/pos', [PosController::class, 'store'])
        ->middleware('branch.scope')
        ->name('staf.pos.store');
    Route::get('/staf/pos/cari-anggota', [PosController::class, 'searchMembers'])
        ->name('staf.pos.search-members');
    Route::get('/staf/pos/cari-rekening-simpanan', [PosController::class, 'searchSavingsAccounts'])
        ->name('staf.pos.search-savings-accounts');
    Route::get('/staf/pos/{sale}/struk', [PosController::class, 'receipt'])
        ->name('staf.pos.receipt');

    Route::get('/admin/pembelian/retur', [PurchaseReturnController::class, 'create'])
        ->name('admin.pembelian.retur.create');
    Route::post('/admin/pembelian/retur', [PurchaseReturnController::class, 'store'])
        ->name('admin.pembelian.retur.store');
    Route::get('/admin/pembelian/retur/{return}/cetak', [PurchaseReturnController::class, 'print'])
        ->name('admin.pembelian.retur.print');

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
    Route::post('/admin/persediaan/koreksi/{adjustment}/batalkan', [StockAdjustmentController::class, 'cancel'])
        ->name('admin.persediaan.koreksi.cancel');
    Route::get('/admin/persediaan/koreksi/{adjustment}/cetak', [StockAdjustmentController::class, 'print'])
        ->name('admin.persediaan.koreksi.print');

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
    Route::get('/admin/aktiva-tetap/export-pdf', [FixedAssetController::class, 'exportPdf'])
        ->name('admin.aktiva-tetap.export-pdf');
    Route::get('/admin/aktiva-tetap/export-excel', [FixedAssetController::class, 'exportExcel'])
        ->name('admin.aktiva-tetap.export-excel');
    Route::get('/admin/aktiva-tetap/tambah', [FixedAssetController::class, 'create'])
        ->name('admin.aktiva-tetap.create');
    Route::post('/admin/aktiva-tetap', [FixedAssetController::class, 'store'])
        ->middleware('branch.scope')
        ->name('admin.aktiva-tetap.store');
    Route::post('/admin/aktiva-tetap/{asset}/keputusan', [FixedAssetController::class, 'decide'])
        ->name('admin.aktiva-tetap.decide');
    Route::get('/admin/aktiva-tetap/{asset}/cetak', [FixedAssetController::class, 'print'])
        ->name('admin.aktiva-tetap.print');
    Route::get('/admin/aktiva-tetap/cetak-barcode', [FixedAssetBarcodeController::class, 'print'])
        ->name('admin.aktiva-tetap.print-barcode');

    Route::get('/admin/aktiva-tetap/pelepasan', [FixedAssetDisposalController::class, 'create'])
        ->name('admin.aktiva-tetap.pelepasan.create');
    Route::post('/admin/aktiva-tetap/pelepasan', [FixedAssetDisposalController::class, 'store'])
        ->name('admin.aktiva-tetap.pelepasan.store');
    Route::post('/admin/aktiva-tetap/pelepasan/{disposal}/batalkan', [FixedAssetDisposalController::class, 'cancel'])
        ->name('admin.aktiva-tetap.pelepasan.cancel');
    Route::get('/admin/aktiva-tetap/pelepasan/{disposal}/cetak', [FixedAssetDisposalController::class, 'print'])
        ->name('admin.aktiva-tetap.pelepasan.print');

    Route::get('/admin/aktiva-tetap/laporan/daftar', [FixedAssetReportController::class, 'daftar'])
        ->name('admin.aktiva-tetap.laporan.daftar');
    Route::get('/admin/aktiva-tetap/laporan/kartu-penyusutan', [FixedAssetReportController::class, 'kartuPenyusutan'])
        ->name('admin.aktiva-tetap.laporan.kartu-penyusutan');
    Route::get('/admin/aktiva-tetap/laporan/ringkasan-penyusutan', [FixedAssetReportController::class, 'ringkasanPenyusutan'])
        ->name('admin.aktiva-tetap.laporan.ringkasan-penyusutan');
    Route::get('/admin/aktiva-tetap/laporan/pelepasan', [FixedAssetReportController::class, 'pelepasan'])
        ->name('admin.aktiva-tetap.laporan.pelepasan');

    // Portal Anggota — akses baca data sendiri + dua form pengajuan
    // (bukan eksekusi langsung, tetap diproses staf). Semua di sini
    // digerbang `member.portal` (EnsureHasLinkedMember) di atas gate
    // 'auth' yang sudah berlaku untuk seluruh grup ini.
    Route::middleware('member.portal')->prefix('portal')->name('portal.')->group(function () {
        Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');

        Route::get('/simpanan', [MemberSavingsController::class, 'index'])->name('savings.index');
        Route::get('/simpanan/{account}', [MemberSavingsController::class, 'history'])->name('savings.history');
        Route::get('/simpanan/{account}/setor', [SavingsDepositController::class, 'create'])->name('savings.deposit.create');
        Route::post('/simpanan/{account}/setor', [SavingsDepositController::class, 'store'])->name('savings.deposit.store');
        Route::get('/simpanan-cetak', [MemberPrintSavingsController::class, 'index'])->name('print.savings');

        Route::get('/pinjaman', [MemberLoanController::class, 'index'])->name('loans.index');
        Route::get('/pinjaman/{loan}', [MemberLoanController::class, 'schedule'])->name('loans.schedule');
        Route::get('/pinjaman/{loan}/bayar', [LoanRepaymentController::class, 'create'])->name('loans.repayment.create');
        Route::post('/pinjaman/{loan}/bayar', [LoanRepaymentController::class, 'store'])->name('loans.repayment.store');
        Route::get('/pinjaman-cetak', [MemberPrintLoanController::class, 'index'])->name('print.loans');
        Route::get('/pinjaman/{loan}/cetak-angsuran', [MemberPrintLoanController::class, 'schedule'])->name('print.loans.schedule');

        Route::get('/pengajuan-pinjaman', [MemberLoanApplicationController::class, 'create'])->name('loan-application.create');
        Route::post('/pengajuan-pinjaman', [MemberLoanApplicationController::class, 'store'])->name('loan-application.store');

        Route::get('/pengajuan-penarikan', [WithdrawalRequestController::class, 'create'])->name('withdrawal-request.create');
        Route::post('/pengajuan-penarikan', [WithdrawalRequestController::class, 'store'])->name('withdrawal-request.store');

        // Halaman terima-kasih generik setelah kembali dari Xendit — bukan
        // bukti pembayaran (itu hanya lewat webhook server-to-server),
        // murni UX untuk kedua alur mandiri di atas.
        Route::get('/pembayaran/selesai', fn () => view('portal.payment-finished'))->name('payment.finished');
    });
});
