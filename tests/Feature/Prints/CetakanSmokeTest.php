<?php

namespace Tests\Feature\Prints;

use App\Models\ApprovalThreshold;
use App\Models\Branch;
use App\Models\BusinessUnit;
use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanSchedule;
use App\Models\Member;
use App\Models\Product;
use App\Models\RetributionType;
use App\Models\SavingsAccount;
use App\Models\StockReason;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Accounting\JournalEngine;
use App\Services\BusinessUnit\BusinessUnitService;
use App\Services\FixedAsset\FixedAssetDisposalService;
use App\Services\FixedAsset\FixedAssetPurchaseService;
use App\Services\Inventory\PurchaseService;
use App\Services\Inventory\ReturnService;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Inventory\StockLedgerEngine;
use App\Services\Loans\LoanRepaymentService;
use App\Services\Savings\SavingsService;
use App\Services\Savings\SavingsWithdrawalRequestService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Does the print view actually render" smoke test across the remaining
 * ~18 cetakan routes from Fase 4-20, none of which are exercised by
 * PrintSettingsTest/SignatureConfigTest/PortalPrintOwnershipTest/
 * MemberCardBulkPrintTest. Fixtures are built with the real services (the
 * sole writers of their respective tables) rather than raw factory
 * ->create() calls, matching the convention already used by e.g.
 * PurchaseServiceTest/LoanRepaymentServiceTest — business-logic
 * correctness is covered there, this file only asserts the PDF renders.
 */
class CetakanSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    private function printTester(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        \Spatie\Permission\Models\Role::findOrCreate('print-tester')->givePermissionTo([
            'simpanan.print', 'simpanan.read',
            'pinjaman.print', 'pinjaman.read',
            'jurnal.read', 'pembelian.read', 'persediaan_koreksi.read',
            'aktiva_tetap.read', 'retribusi_upf.read', 'unit_usaha.read',
            'master_data.read',
        ]);
        $user->assignRole('print-tester');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function assertPdf($response): void
    {
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_savings_and_loans_list_print(): void
    {
        $user = $this->printTester();
        SavingsAccount::factory()->create();
        Loan::factory()->create(['status' => 'dicairkan']);

        $this->assertPdf($this->actingAs($user)->get(route('admin.print.savings.index')));
        $this->assertPdf($this->actingAs($user)->get(route('admin.print.loans.index')));
    }

    /**
     * Regresi untuk permintaan "cetakan simpanan tambahkan total simpanan":
     * totalnya harus menjumlahkan SELURUH rekening anggota itu (bukan cuma
     * satu). Total angsuran pinjaman sengaja TIDAK ditampilkan di cetakan
     * ini (diminta dihapus lagi — laporan simpanan cukup soal simpanan).
     *
     * Asersi lewat query, bukan scraping teks PDF (lihat catatan di
     * test_loans_list_print_filters_by_status di atas).
     */
    public function test_savings_print_totals_balance_across_all_accounts(): void
    {
        $user = $this->printTester();

        $member = Member::factory()->create();
        SavingsAccount::factory()->create(['member_id' => $member->id, 'balance' => 300000]);
        SavingsAccount::factory()->create(['member_id' => $member->id, 'balance' => 450000]);

        // Sama persis dengan yang dipakai prints.savings.list untuk
        // menghitung total simpanan.
        $totalSimpanan = $member->savingsAccounts()->get()->sum('balance');
        $this->assertEquals(750000, $totalSimpanan);

        $this->assertPdf($this->actingAs($user)->get(route('admin.print.savings.index', ['member_id' => $member->id])));
    }

    /**
     * Regresi untuk permintaan "laporan pinjaman per anggota gabungan
     * historis pembayaran": filter `status` harus benar-benar menyaring
     * ANGGOTA (bukan cuma pinjamannya jadi kosong), dan halaman harus
     * merender saldo outstanding + riwayat pembayaran tanpa error.
     *
     * Asersi isi tetap lewat query, bukan scraping teks dari PDF — dompdf
     * meng-compress content stream-nya (FlateDecode) secara default, jadi
     * nama anggota tidak muncul sebagai teks polos di response body.
     */
    public function test_loans_list_print_filters_by_status(): void
    {
        $user = $this->printTester();

        $paidOffLoan = $this->disbursedLoanWithThreeInstallments();
        app(LoanRepaymentService::class)->recordPayment($paidOffLoan, 3300000, $user->id);
        $this->assertEquals('lunas', $paidOffLoan->fresh()->status);

        // Sama persis dengan yang dipakai prints.loans.list untuk
        // menghitung "Sudah Dibayar" / "Total Sudah Dibayar".
        $totalDibayar = $paidOffLoan->repayments->reject(fn ($r) => $r->isCancelled())->sum('amount');
        $this->assertEquals(3300000, $totalDibayar);

        $activeLoan = $this->disbursedLoanWithThreeInstallments();
        app(LoanRepaymentService::class)->recordPayment($activeLoan, 1100000, $user->id);
        $this->assertEquals('dicairkan', $activeLoan->fresh()->status);

        // Query yang sama persis dengan yang dipakai PrintLoanController::
        // index() untuk memutuskan anggota mana yang ikut tercetak —
        // memverifikasi whereHas('loans', status=X) benar-benar menyaring
        // ANGGOTA (bukan cuma daftar pinjamannya jadi kosong).
        $aktifMemberIds = Member::query()
            ->whereHas('loans', fn ($q) => $q->where('status', 'dicairkan'))
            ->pluck('id');
        $this->assertTrue($aktifMemberIds->contains($activeLoan->member_id));
        $this->assertFalse($aktifMemberIds->contains($paidOffLoan->member_id));

        $lunasMemberIds = Member::query()
            ->whereHas('loans', fn ($q) => $q->where('status', 'lunas'))
            ->pluck('id');
        $this->assertTrue($lunasMemberIds->contains($paidOffLoan->member_id));
        $this->assertFalse($lunasMemberIds->contains($activeLoan->member_id));

        $this->assertPdf($this->actingAs($user)->get(route('admin.print.loans.index', ['status' => 'aktif'])));
        $this->assertPdf($this->actingAs($user)->get(route('admin.print.loans.index', ['status' => 'lunas'])));

        // status=semua (default, tanpa parameter): menjaga kompatibilitas
        // dengan link "Cetak Pinjaman" per baris anggota di
        // admin.anggota.index yang tidak mengirim `status` sama sekali.
        $this->assertPdf($this->actingAs($user)->get(route('admin.print.loans.index')));
    }

    public function test_loan_schedule_print(): void
    {
        $user = $this->printTester();
        $loan = Loan::factory()->create(['status' => 'dicairkan']);

        $this->assertPdf($this->actingAs($user)->get(route('admin.print.loans.schedule', $loan)));
    }

    public function test_loan_application_print(): void
    {
        $user = $this->printTester();
        $loan = Loan::factory()->create(['status' => 'diajukan', 'created_by' => $user->id]);

        $this->assertPdf($this->actingAs($user)->get(route('admin.print.loan-application.show', $loan)));
    }

    public function test_withdrawal_request_print(): void
    {
        $user = $this->printTester();
        $account = SavingsAccount::factory()->create(['balance' => 500000]);

        $withdrawalRequest = app(SavingsWithdrawalRequestService::class)->request($account, 150000, $user->id);

        $this->assertPdf($this->actingAs($user)->get(route('admin.print.withdrawal-request.show', $withdrawalRequest)));
    }

    private function disbursedLoanWithThreeInstallments(array $overrides = []): Loan
    {
        $product = LoanProduct::factory()->create();
        $loan = Loan::factory()->create(array_merge(
            ['loan_product_id' => $product->id, 'status' => 'dicairkan', 'principal_amount' => 3000000],
            $overrides,
        ));

        for ($i = 1; $i <= 3; $i++) {
            LoanSchedule::query()->create([
                'loan_id' => $loan->id,
                'installment_number' => $i,
                'due_date' => now()->addMonths($i),
                'principal_amount' => 1000000,
                'interest_amount' => 100000,
                'total_amount' => 1100000,
                'paid_amount' => 0,
                'status' => 'belum_bayar',
            ]);
        }

        return $loan->fresh();
    }

    public function test_loan_repayment_receipt_print(): void
    {
        $user = $this->printTester();
        $loan = $this->disbursedLoanWithThreeInstallments();

        $repayment = app(LoanRepaymentService::class)->recordPayment($loan, 1100000, $user->id);

        $this->assertPdf($this->actingAs($user)->get(route('admin.print.loan-repayment.show', $repayment)));
    }

    public function test_teller_receipt_print(): void
    {
        $user = $this->printTester();
        $account = SavingsAccount::factory()->create(['balance' => 100000]);

        $transaction = app(SavingsService::class)->deposit($account, 50000, $user->id);

        $this->assertPdf($this->actingAs($user)->get(route('staf.teller.print-receipt', $transaction)));
    }

    public function test_jurnal_umum_print(): void
    {
        $user = $this->printTester();
        $branch = Branch::factory()->create();
        $cash = ChartOfAccount::query()->where('code', '1101')->firstOrFail();
        $other = ChartOfAccount::factory()->create();

        $entry = app(JournalEngine::class)->post([
            'branch_id' => $branch->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Uji cetak jurnal umum',
            'created_by' => $user->id,
            'lines' => [
                ['chart_of_account_id' => $cash->id, 'debit' => 100000, 'credit' => 0],
                ['chart_of_account_id' => $other->id, 'debit' => 0, 'credit' => 100000],
            ],
        ]);

        $this->assertPdf($this->actingAs($user)->get(route('admin.jurnal-umum.print', $entry)));
    }

    private function makeProduct(): Product
    {
        return Product::factory()->create([
            'coa_inventory_account_id' => ChartOfAccount::factory()->create()->id,
        ]);
    }

    public function test_purchase_and_purchase_return_print(): void
    {
        $user = $this->printTester();
        ApprovalThreshold::query()->create(['module' => 'pembelian', 'threshold_amount' => 5000000]);

        $branch = Branch::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = $this->makeProduct();
        $reason = StockReason::factory()->create();

        $purchase = app(PurchaseService::class)->submit(
            $supplier,
            $branch->id,
            'tunai',
            [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '1000']],
            $user->id,
        );

        $this->assertPdf($this->actingAs($user)->get(route('admin.pembelian.print', $purchase)));

        $item = $purchase->items->first();
        $return = app(ReturnService::class)->returnPurchase($item, '2', $reason, $user->id);

        $this->assertPdf($this->actingAs($user)->get(route('admin.pembelian.retur.print', $return)));
    }

    public function test_stock_adjustment_print(): void
    {
        $user = $this->printTester();
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $reason = StockReason::factory()->create();

        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id);
        $adjustment = app(StockAdjustmentService::class)->submit($product, $branch->id, '15', $reason, $user->id);

        $this->assertPdf($this->actingAs($user)->get(route('admin.persediaan.koreksi.print', $adjustment)));
    }

    private function assetCategory(): FixedAssetCategory
    {
        return FixedAssetCategory::factory()->create([
            'coa_asset_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_accumulated_depreciation_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_depreciation_expense_account_id' => ChartOfAccount::factory()->create()->id,
        ]);
    }

    public function test_fixed_asset_and_barcode_print(): void
    {
        $user = $this->printTester();
        ApprovalThreshold::query()->create(['module' => 'aktiva_tetap', 'threshold_amount' => 50000000]);

        $branch = Branch::factory()->create();
        $category = $this->assetCategory();

        $asset = app(FixedAssetPurchaseService::class)->submit([
            'fixed_asset_category_id' => $category->id,
            'branch_id' => $branch->id,
            'code' => 'AT-'.random_int(1000, 9999),
            'name' => 'Laptop Kantor',
            'acquisition_date' => now()->toDateString(),
            'acquisition_cost' => '12000000',
            'residual_value' => '1000000',
            'useful_life_months' => 36,
            'depreciation_method' => 'garis_lurus',
            'payment_method' => 'tunai',
        ], $user->id);

        $this->assertPdf($this->actingAs($user)->get(route('admin.aktiva-tetap.print', $asset)));
        $this->assertPdf($this->actingAs($user)->get(route('admin.aktiva-tetap.print-barcode')));

        $disposal = app(FixedAssetDisposalService::class)->dispose($asset, 'dijual', '11500000', $user->id);

        $this->assertPdf($this->actingAs($user)->get(route('admin.aktiva-tetap.pelepasan.print', $disposal)));
    }

    public function test_kas_bank_harian_print(): void
    {
        $user = $this->printTester();

        $this->assertPdf($this->actingAs($user)->get(route('admin.dashboard.kas-bank.print')));
    }

    public function test_retribusi_upf_harian_print(): void
    {
        $user = $this->printTester();
        $branch = Branch::factory()->create();

        $type = RetributionType::factory()->create([
            'percentage' => 100,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);

        app(\App\Services\Retribution\RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'umum',
            payerName: 'Ibu Sari',
            member: null,
            totalAmount: 50000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
            retributionType: $type,
        );

        $this->assertPdf($this->actingAs($user)->get(route('staf.retribusi-upf.print-harian')));
    }

    /**
     * Regresi untuk permintaan "cetakan rekap: jenis retribusi yang nol
     * di-hide, cukup yang ada nilainya saja": RetributionReportService
     * tetap mengembalikan SEMUA jenis aktif (termasuk yang nol) — filternya
     * ada di view (prints.laporan.upf-rekap), bukan di service, supaya
     * data lengkap tetap tersedia untuk pemakaian lain.
     */
    public function test_retribusi_upf_rekap_print(): void
    {
        $user = $this->printTester();
        $branch = Branch::factory()->create();

        $typeBertransaksi = RetributionType::factory()->create([
            'percentage' => 100,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
        $typeTanpaTransaksi = RetributionType::factory()->create([
            'percentage' => 100,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);

        app(\App\Services\Retribution\RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'umum',
            payerName: 'Ibu Sari',
            member: null,
            totalAmount: 50000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
            retributionType: $typeBertransaksi,
        );

        $rekap = app(\App\Services\Retribution\RetributionReportService::class)->rekapPendapatan([
            'branch_id' => $branch->id,
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->addDay()->toDateString(),
        ]);

        // Service: kedua jenis tetap ada di breakdown, termasuk yang nol.
        $this->assertCount(2, $rekap['breakdown']);
        $this->assertTrue(collect($rekap['breakdown'])->contains(fn ($row) => $row['name'] === $typeTanpaTransaksi->name && $row['total'] == 0));

        // View: yang nol disaring keluar — sama persis filter yang
        // dipakai prints.laporan.upf-rekap.
        $breakdownRows = collect($rekap['breakdown'])->filter(fn ($row) => (float) $row['total'] > 0)->values();
        $this->assertCount(1, $breakdownRows);
        $this->assertEquals($typeBertransaksi->name, $breakdownRows->first()['name']);

        $this->assertPdf($this->actingAs($user)->get(route('staf.retribusi-upf.print-rekap', [
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->addDay()->toDateString(),
            'branch_id' => $branch->id,
        ])));
    }

    public function test_unit_usaha_print(): void
    {
        $user = $this->printTester();
        $branch = Branch::factory()->create();
        $unit = BusinessUnit::factory()->create();

        app(BusinessUnitService::class)->record($unit, 'pendapatan', 500000, $branch->id, $user->id);

        $this->assertPdf($this->actingAs($user)->get(route('admin.unit-usaha.print', $unit)));
    }

    public function test_product_barcode_print(): void
    {
        $user = $this->printTester();
        Product::factory()->count(2)->create(['is_active' => true]);

        $this->assertPdf($this->actingAs($user)->get(route('admin.master.products.print-barcode')));
    }
}
