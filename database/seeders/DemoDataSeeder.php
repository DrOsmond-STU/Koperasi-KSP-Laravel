<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BusinessUnit;
use App\Models\ChartOfAccount;
use App\Models\CooperativeEvent;
use App\Models\FixedAssetCategory;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\Product;
use App\Models\RetributionType;
use App\Models\SavingsProduct;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Accounting\JournalEngine;
use App\Services\BusinessUnit\BusinessUnitService;
use App\Services\Calendar\CooperativeEventService;
use App\Services\FixedAsset\FixedAssetPurchaseService;
use App\Services\Inventory\PurchaseService;
use App\Services\Loans\LoanApprovalService;
use App\Services\Loans\LoanRepaymentService;
use App\Services\Loans\LoanService;
use App\Services\Pos\PosSaleService;
use App\Services\Retribution\RetributionService;
use App\Services\Savings\SavingsService;
use Illuminate\Database\Seeder;

/**
 * Populates realistic demo/dummy data across every transactional module so
 * the app can be browsed end-to-end for gap-checking. Not part of
 * DatabaseSeeder's default run() — invoke manually with
 * `php artisan db:seed --class=DemoDataSeeder`. Safe to re-run for the
 * master-data parts (users/members/products/suppliers use firstOrCreate),
 * but transactional calls (loans, purchases, savings deposits, etc.) create
 * fresh records every run by design.
 */
class DemoDataSeeder extends Seeder
{
    private User $petugasKredit;

    private User $manajer;

    private User $bendahara;

    private User $teller;

    private User $petugasUpf;

    public function run(): void
    {
        $this->createTestUsers();
        $this->seedOpeningCapital();
        $members = $this->createMembers();
        $this->createSavings($members);
        $this->createLoans($members);
        [$products, $suppliers] = $this->createProductsAndSuppliers();
        $this->createPurchasesAndSales($products, $suppliers);
        $this->createFixedAssets();
        $this->createRetribution($members);
        $this->createBusinessUnits();
        $this->createCalendarEvents($members);
    }

    private function createTestUsers(): void
    {
        $roles = [
            'admin.sistem@ksp.test' => ['name' => 'Test Admin Sistem', 'role' => 'admin_sistem'],
            'manajer@ksp.test' => ['name' => 'Test Manajer', 'role' => 'manajer'],
            'teller@ksp.test' => ['name' => 'Test Teller', 'role' => 'teller'],
            'kredit@ksp.test' => ['name' => 'Test Petugas Kredit', 'role' => 'petugas_kredit'],
            'upf@ksp.test' => ['name' => 'Test Petugas UPF', 'role' => 'petugas_upf'],
            'bendahara@ksp.test' => ['name' => 'Test Bendahara', 'role' => 'bendahara'],
            'pengawas@ksp.test' => ['name' => 'Test Pengawas', 'role' => 'pengawas'],
        ];

        foreach ($roles as $email => $info) {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $info['name'],
                    'password' => 'Password123!',
                    'two_factor_confirmed_at' => now(),
                ]
            );

            if (! $user->two_factor_confirmed_at) {
                $user->forceFill(['two_factor_confirmed_at' => now()])->save();
            }

            if (! $user->hasRole($info['role'])) {
                $user->assignRole($info['role']);
            }

            UserBranchScope::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['scope_type' => 'all']
            );
        }

        $this->petugasKredit = User::query()->where('email', 'kredit@ksp.test')->first();
        $this->manajer = User::query()->where('email', 'manajer@ksp.test')->first();
        $this->bendahara = User::query()->where('email', 'bendahara@ksp.test')->first();
        $this->teller = User::query()->where('email', 'teller@ksp.test')->first();
        $this->petugasUpf = User::query()->where('email', 'upf@ksp.test')->first();
    }

    /**
     * Modal awal koperasi (simpanan pokok/wajib + modal penyertaan), diposting
     * sebagai satu jurnal Dr Kas / Cr Ekuitas sebelum transaksi demo lain
     * dibuat. Tanpa ini Neraca akan selalu tampak "aneh" (Aset negatif) karena
     * semua pinjaman/pembelian/aktiva tetap demo mengalir keluar dari kas
     * tanpa modal awal yang mendanainya — persis seperti koperasi baru yang
     * langsung beroperasi tanpa pernah menghimpun modal dari anggotanya.
     */
    private function seedOpeningCapital(): void
    {
        $description = 'Modal awal koperasi (data demo)';

        if (\App\Models\JournalEntry::query()->where('description', $description)->exists()) {
            return;
        }

        $branchId = Branch::query()->where('name', 'Kantor Pusat')->value('id');
        $kas = ChartOfAccount::query()->where('code', '1101')->firstOrFail();
        $simpananPokok = ChartOfAccount::query()->where('code', '3101')->firstOrFail();
        $simpananWajib = ChartOfAccount::query()->where('code', '3102')->firstOrFail();
        $modalPenyertaan = ChartOfAccount::query()->where('code', '3110')->firstOrFail();

        app(JournalEngine::class)->post([
            'branch_id' => $branchId,
            'entry_date' => now()->subDays(60)->toDateString(),
            'description' => $description,
            'created_by' => $this->bendahara->id,
            'lines' => [
                ['chart_of_account_id' => $kas->id, 'debit' => 65000000, 'credit' => 0],
                ['chart_of_account_id' => $simpananPokok->id, 'debit' => 0, 'credit' => 1000000],
                ['chart_of_account_id' => $simpananWajib->id, 'debit' => 0, 'credit' => 6000000],
                ['chart_of_account_id' => $modalPenyertaan->id, 'debit' => 0, 'credit' => 58000000],
            ],
        ]);
    }

    private function demoSavingsProduct(): SavingsProduct
    {
        $product = SavingsProduct::query()->firstOrCreate(
            ['code' => 'SIM-DEMO'],
            [
                'name' => 'Simpanan Sukarela Demo',
                'category' => 'sukarela',
                'interest_method' => 'flat',
                'minimum_initial_deposit' => 10000,
                'minimum_subsequent_deposit' => 5000,
                'coa_liability_account_id' => ChartOfAccount::query()->where('code', '2101')->firstOrFail()->id,
                'coa_interest_expense_account_id' => ChartOfAccount::query()->where('code', '5102')->firstOrFail()->id,
                'is_active' => true,
            ]
        );

        $product->rateHistory()->firstOrCreate(
            ['savings_product_id' => $product->id],
            ['rate_percentage' => 2.5, 'effective_from' => now()->subYear()->toDateString()],
        );

        return $product;
    }

    private function demoLoanProduct(): LoanProduct
    {
        $product = LoanProduct::query()->firstOrCreate(
            ['code' => 'PINJ-DEMO'],
            [
                'name' => 'Pinjaman Modal Usaha Demo',
                'min_plafon' => 500000,
                'max_plafon' => 50000000,
                'min_tenor_days' => 7,
                'max_tenor_days' => 180,
                'calculation_method' => 'flat',
                'provision_fee_percentage' => 1,
                'penalty_percentage_per_day' => 0.1,
                'approval_threshold' => 10000000,
                'coa_receivable_account_id' => ChartOfAccount::query()->where('code', '1120')->firstOrFail()->id,
                'coa_interest_income_account_id' => ChartOfAccount::query()->where('code', '4101')->firstOrFail()->id,
                'coa_provision_income_account_id' => ChartOfAccount::query()->where('code', '4102')->firstOrFail()->id,
                'coa_penalty_receivable_account_id' => ChartOfAccount::query()->where('code', '1133')->firstOrFail()->id,
                'is_active' => true,
            ]
        );

        $product->rateHistory()->firstOrCreate(
            ['loan_product_id' => $product->id],
            ['rate_percentage' => 12.0, 'effective_from' => now()->subYear()->toDateString()],
        );

        return $product;
    }

    /**
     * @return array<int, Member>
     */
    private function createMembers(): array
    {
        $branchIds = Branch::query()->pluck('id', 'name');
        $biasa = MemberType::query()->find(1) ?? MemberType::factory()->create(['name' => 'Anggota Biasa']);
        $kios = MemberType::query()->find(3) ?? MemberType::factory()->create(['name' => 'Anggota Kios']);
        $blok = MemberType::query()->find(4) ?? MemberType::factory()->create(['name' => 'Anggota Blok']);

        $defs = [
            ['number' => 'AGT-0101', 'name' => 'Hendra Wijaya', 'type' => $biasa->id, 'branch' => 'Kantor Pusat'],
            ['number' => 'AGT-0102', 'name' => 'Rini Marlina', 'type' => $biasa->id, 'branch' => 'Kantor Pusat'],
            ['number' => 'AGT-0103', 'name' => 'Agus Setiawan', 'type' => $biasa->id, 'branch' => 'Cabang Palangka Raya'],
            ['number' => 'AGT-0104', 'name' => 'Yuliana Putri', 'type' => $biasa->id, 'branch' => 'Cabang Batam'],
            ['number' => 'AGT-0105', 'name' => 'Toko Sumber Makmur (Kios)', 'type' => $kios->id, 'branch' => 'Kantor Pusat'],
            ['number' => 'AGT-0106', 'name' => 'Warung Blok C-12', 'type' => $blok->id, 'branch' => 'Kantor Pusat'],
        ];

        $members = [];

        foreach ($defs as $def) {
            $members[] = Member::query()->firstOrCreate(
                ['member_number' => $def['number']],
                [
                    'branch_id' => $branchIds[$def['branch']] ?? Branch::query()->first()->id,
                    'member_type_id' => $def['type'],
                    'name' => $def['name'],
                    'nik' => fake()->numerify('################'),
                    'address' => fake()->address(),
                    'phone' => '628'.fake()->numerify('##########'),
                    'email' => fake()->unique()->safeEmail(),
                    'date_of_birth' => fake()->date(),
                    'status' => 'aktif',
                    'joined_at' => now()->subMonths(random_int(1, 24))->toDateString(),
                ]
            );
        }

        return $members;
    }

    /**
     * @param  array<int, Member>  $members
     */
    private function createSavings(array $members): void
    {
        $savingsService = app(SavingsService::class);
        $product = $this->demoSavingsProduct();

        foreach ($members as $index => $member) {
            if ($member->savingsAccounts()->where('savings_product_id', $product->id)->exists()) {
                continue;
            }

            $initial = [500000, 750000, 1000000, 300000, 2000000, 1500000][$index] ?? 500000;
            $account = $savingsService->openAccount($member, $product, $member->branch_id, (float) $initial, $this->teller->id);

            // A couple of extra deposits so the transaction history/report has depth.
            $savingsService->deposit($account, (float) random_int(1, 3) * 100000, $this->teller->id, 'Setoran rutin bulanan');
            $account->refresh();

            if ($index % 3 === 0 && (float) $account->balance > 100000) {
                $savingsService->withdraw($account, 100000, $this->teller->id, 'Tarik tunai kebutuhan mendesak');
            }
        }
    }

    /**
     * @param  array<int, Member>  $members
     */
    private function createLoans(array $members): void
    {
        $loanService = app(LoanService::class);
        $approvalService = app(LoanApprovalService::class);
        $repaymentService = app(LoanRepaymentService::class);
        $product = $this->demoLoanProduct();

        if (Loan::query()->exists()) {
            return;
        }

        // 1) Diajukan — belum diputuskan sama sekali.
        $loanService->submitApplication($members[0], $product, 3000000, 30, $members[0]->branch_id, $this->petugasKredit->id);

        // 2) Ditolak.
        $rejected = $loanService->submitApplication($members[1], $product, 2000000, 15, $members[1]->branch_id, $this->petugasKredit->id);
        $approvalService->reject($rejected, $this->manajer, 'Riwayat angsuran sebelumnya kurang lancar.');

        // 3) Dicairkan, dengan 2 kali pembayaran angsuran sebagian.
        $active1 = $loanService->submitApplication($members[2], $product, 5000000, 30, $members[2]->branch_id, $this->petugasKredit->id);
        $approvalService->approve($active1, $this->manajer);
        $active1 = $active1->fresh();
        $firstInstallment = (float) $active1->schedules()->orderBy('installment_number')->first()->total_amount;
        $repaymentService->recordPayment($active1, $firstInstallment, $this->teller->id, 'Pembayaran angsuran ke-1');

        // 4) Dicairkan, belum ada pembayaran (untuk uji pengingat jatuh tempo).
        $active2 = $loanService->submitApplication($members[3], $product, 8000000, 60, $members[3]->branch_id, $this->petugasKredit->id);
        $approvalService->approve($active2, $this->bendahara);

        // 5) Di atas ambang approval (butuh 2 persetujuan berjenjang).
        $bigLoan = $loanService->submitApplication($members[4], $product, 15000000, 100, $members[4]->branch_id, $this->petugasKredit->id);
        $approvalService->approve($bigLoan, $this->manajer);
        $approvalService->approve($bigLoan->fresh(), $this->bendahara);
    }

    /**
     * @return array{0: array<int, Product>, 1: array<int, Supplier>}
     */
    private function createProductsAndSuppliers(): array
    {
        $inventory = ChartOfAccount::query()->where('code', '1140')->firstOrFail();
        $cogs = ChartOfAccount::query()->where('code', '5101')->firstOrFail();
        $salesRevenue = ChartOfAccount::query()->where('code', '4201')->firstOrFail();
        $payable = ChartOfAccount::query()->where('code', '2110')->firstOrFail();

        $supplierDefs = [
            ['code' => 'SUP-001', 'name' => 'CV Sumber Rejeki', 'term' => 'kredit', 'days' => 30],
            ['code' => 'SUP-002', 'name' => 'PT Distribusi Sembako Jaya', 'term' => 'tunai', 'days' => null],
        ];

        $suppliers = [];
        foreach ($supplierDefs as $def) {
            $suppliers[] = Supplier::query()->firstOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'type' => 'distributor',
                    'contact_name' => fake()->name(),
                    'phone' => '628'.fake()->numerify('##########'),
                    'address' => fake()->address(),
                    'payment_term' => $def['term'],
                    'payment_term_days' => $def['days'],
                    'coa_payable_account_id' => $payable->id,
                    'is_active' => true,
                ]
            );
        }

        $productDefs = [
            ['code' => 'BRG-001', 'name' => 'Beras 5kg', 'unit' => 'karung', 'buy' => 62000, 'sell' => 68000],
            ['code' => 'BRG-002', 'name' => 'Minyak Goreng 1L', 'unit' => 'botol', 'buy' => 15500, 'sell' => 18000],
            ['code' => 'BRG-003', 'name' => 'Gula Pasir 1kg', 'unit' => 'kg', 'buy' => 13500, 'sell' => 16000],
            ['code' => 'BRG-004', 'name' => 'Telur Ayam 1kg', 'unit' => 'kg', 'buy' => 24000, 'sell' => 28000],
            ['code' => 'BRG-005', 'name' => 'Kopi Sachet (renceng)', 'unit' => 'renceng', 'buy' => 9500, 'sell' => 12000],
            ['code' => 'BRG-006', 'name' => 'Mie Instan (dus)', 'unit' => 'dus', 'buy' => 95000, 'sell' => 110000],
            ['code' => 'BRG-007', 'name' => 'Sabun Mandi', 'unit' => 'pcs', 'buy' => 3200, 'sell' => 4500],
            ['code' => 'BRG-008', 'name' => 'Air Mineral 600ml (dus)', 'unit' => 'dus', 'buy' => 32000, 'sell' => 40000],
            ['code' => 'BRG-009', 'name' => 'Tepung Terigu 1kg', 'unit' => 'kg', 'buy' => 10500, 'sell' => 13000],
            ['code' => 'BRG-010', 'name' => 'Kecap Manis 600ml', 'unit' => 'botol', 'buy' => 14000, 'sell' => 17500],
        ];

        $products = [];
        foreach ($productDefs as $def) {
            $products[] = Product::query()->firstOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'category' => 'Sembako',
                    'unit' => $def['unit'],
                    'purchase_price' => $def['buy'],
                    'selling_price' => $def['sell'],
                    'coa_inventory_account_id' => $inventory->id,
                    'coa_cogs_account_id' => $cogs->id,
                    'coa_sales_revenue_account_id' => $salesRevenue->id,
                    'is_active' => true,
                ]
            );
        }

        return [$products, $suppliers];
    }

    /**
     * @param  array<int, Product>  $products
     * @param  array<int, Supplier>  $suppliers
     */
    private function createPurchasesAndSales(array $products, array $suppliers): void
    {
        $purchaseService = app(PurchaseService::class);
        $posSaleService = app(PosSaleService::class);
        $branchId = Branch::query()->where('name', 'Kantor Pusat')->value('id');

        if (\App\Models\PurchaseTransaction::query()->exists()) {
            return;
        }

        // Kredit, di atas ambang approval (Rp 5.000.000) -> menunggu approval lalu disetujui.
        $bigPurchase = $purchaseService->submit(
            $suppliers[0],
            $branchId,
            'kredit',
            [
                ['product_id' => $products[0]->id, 'qty' => '50', 'unit_price' => (string) $products[0]->purchase_price],
                ['product_id' => $products[1]->id, 'qty' => '80', 'unit_price' => (string) $products[1]->purchase_price],
                ['product_id' => $products[5]->id, 'qty' => '30', 'unit_price' => (string) $products[5]->purchase_price],
            ],
            $this->petugasKredit->id,
        );
        $purchaseService->approve($bigPurchase, $this->manajer);

        // Tunai, di bawah ambang -> langsung diposting.
        $purchaseService->submit(
            $suppliers[1],
            $branchId,
            'tunai',
            [
                ['product_id' => $products[2]->id, 'qty' => '40', 'unit_price' => (string) $products[2]->purchase_price],
                ['product_id' => $products[3]->id, 'qty' => '20', 'unit_price' => (string) $products[3]->purchase_price],
                ['product_id' => $products[6]->id, 'qty' => '60', 'unit_price' => (string) $products[6]->purchase_price],
                ['product_id' => $products[8]->id, 'qty' => '25', 'unit_price' => (string) $products[8]->purchase_price],
            ],
            $this->petugasKredit->id,
        );

        // Penjualan POS tunai, menggunakan stok yang baru diterima dari pembelian tunai di atas.
        $posSaleService->sell($branchId, 'tunai', [
            ['product_id' => $products[2]->id, 'qty' => '5'],
            ['product_id' => $products[6]->id, 'qty' => '3'],
        ], $this->teller->id);

        $posSaleService->sell($branchId, 'tunai', [
            ['product_id' => $products[3]->id, 'qty' => '2'],
            ['product_id' => $products[8]->id, 'qty' => '4'],
        ], $this->teller->id);
    }

    private function createFixedAssets(): void
    {
        $fixedAssetService = app(FixedAssetPurchaseService::class);
        $branchId = Branch::query()->where('name', 'Kantor Pusat')->value('id');

        if (\App\Models\FixedAsset::query()->exists()) {
            return;
        }

        $equipmentCategory = FixedAssetCategory::query()->firstOrCreate(
            ['code' => 'KAT-PRL'],
            [
                'name' => 'Peralatan Kantor',
                'default_depreciation_method' => 'garis_lurus',
                'default_useful_life_months' => 48,
                'coa_asset_account_id' => ChartOfAccount::query()->where('code', '1230')->firstOrFail()->id,
                'coa_accumulated_depreciation_account_id' => ChartOfAccount::query()->where('code', '1231')->firstOrFail()->id,
                'coa_depreciation_expense_account_id' => ChartOfAccount::query()->where('code', '5204')->firstOrFail()->id,
                'is_active' => true,
            ]
        );

        $vehicleCategory = FixedAssetCategory::query()->firstOrCreate(
            ['code' => 'KAT-KDR'],
            [
                'name' => 'Kendaraan',
                'default_depreciation_method' => 'garis_lurus',
                'default_useful_life_months' => 96,
                'coa_asset_account_id' => ChartOfAccount::query()->where('code', '1240')->firstOrFail()->id,
                'coa_accumulated_depreciation_account_id' => ChartOfAccount::query()->where('code', '1241')->firstOrFail()->id,
                'coa_depreciation_expense_account_id' => ChartOfAccount::query()->where('code', '5204')->firstOrFail()->id,
                'is_active' => true,
            ]
        );

        // Di bawah ambang (Rp 10.000.000) -> langsung aktif.
        $fixedAssetService->submit([
            'fixed_asset_category_id' => $equipmentCategory->id,
            'branch_id' => $branchId,
            'code' => 'AT-0001',
            'name' => 'Laptop Kantor Lenovo',
            'acquisition_date' => now()->subMonths(3)->toDateString(),
            'acquisition_cost' => '8000000',
            'residual_value' => '500000',
            'useful_life_months' => 36,
            'depreciation_method' => 'garis_lurus',
            'payment_method' => 'tunai',
        ], $this->bendahara->id);

        $fixedAssetService->submit([
            'fixed_asset_category_id' => $equipmentCategory->id,
            'branch_id' => $branchId,
            'code' => 'AT-0002',
            'name' => 'Printer Multifungsi',
            'acquisition_date' => now()->subMonths(1)->toDateString(),
            'acquisition_cost' => '3500000',
            'residual_value' => '200000',
            'useful_life_months' => 24,
            'depreciation_method' => 'garis_lurus',
            'payment_method' => 'tunai',
        ], $this->bendahara->id);

        // Di atas ambang -> menunggu approval, lalu disetujui.
        $vehicle = $fixedAssetService->submit([
            'fixed_asset_category_id' => $vehicleCategory->id,
            'branch_id' => $branchId,
            'code' => 'AT-0003',
            'name' => 'Sepeda Motor Operasional',
            'acquisition_date' => now()->subDays(10)->toDateString(),
            'acquisition_cost' => '22000000',
            'residual_value' => '4000000',
            'useful_life_months' => 60,
            'depreciation_method' => 'garis_lurus',
            'payment_method' => 'tunai',
        ], $this->bendahara->id);
        $fixedAssetService->approve($vehicle, $this->manajer);
    }

    /**
     * @param  array<int, Member>  $members
     */
    private function createRetribution(array $members): void
    {
        if (RetributionType::query()->where('is_active', true)->exists()) {
            return;
        }

        // Jenis retribusi "split" (persentase < 100, jumlahnya HARUS 100) —
        // dipakai untuk pembagian otomatis pada transaksi Anggota.
        $splitAccounts = [
            '01' => ['name' => 'Retribusi Kebersihan', 'code' => '4302', 'pct' => 30, 'sort' => 1],
            '02' => ['name' => 'Retribusi Keamanan', 'code' => '4303', 'pct' => 25, 'sort' => 2],
            '03' => ['name' => 'Retribusi Listrik', 'code' => '4304', 'pct' => 20, 'sort' => 3],
            '04' => ['name' => 'Retribusi Air', 'code' => '4305', 'pct' => 15, 'sort' => 4],
            '05' => ['name' => 'Retribusi Lainnya', 'code' => '4306', 'pct' => 10, 'sort' => 5],
        ];

        foreach ($splitAccounts as $typeCode => $def) {
            RetributionType::query()->updateOrCreate(
                ['code' => $typeCode],
                [
                    'name' => $def['name'],
                    'percentage' => $def['pct'],
                    'coa_revenue_account_id' => ChartOfAccount::query()->where('code', $def['code'])->firstOrFail()->id,
                    'sort_order' => $def['sort'],
                    'is_active' => true,
                ]
            );
        }

        // Jenis retribusi "umum" (persentase = 100, satu jenis satu transaksi)
        // — dipakai untuk transaksi Umum (bukan anggota). Contoh bawaan:
        // sewa fasilitas & jasa fasilitas lain di luar iuran bulanan.
        $umumAccounts = [
            '06' => ['name' => 'Retribusi Sewa Fasilitas', 'code' => '4301', 'sort' => 10],
            '07' => ['name' => 'Retribusi Jasa Fasilitas Lain', 'code' => '4306', 'sort' => 11],
        ];

        foreach ($umumAccounts as $typeCode => $def) {
            RetributionType::query()->updateOrCreate(
                ['code' => $typeCode],
                [
                    'name' => $def['name'],
                    'percentage' => 100,
                    'coa_revenue_account_id' => ChartOfAccount::query()->where('code', $def['code'])->firstOrFail()->id,
                    'sort_order' => $def['sort'],
                    'is_active' => true,
                ]
            );
        }

        $retributionService = app(RetributionService::class);
        $branchId = Branch::query()->where('name', 'Kantor Pusat')->value('id');
        $sewaType = RetributionType::query()->where('code', '06')->first();

        // Kios/Blok anggota (index 4 & 5 dari createMembers()) → split otomatis.
        $retributionService->record($branchId, 'anggota', null, $members[4], 150000, 'tunai', $this->petugasUpf->id, 'Iuran bulanan kios');
        $retributionService->record($branchId, 'anggota', null, $members[5], 100000, 'tunai', $this->petugasUpf->id, 'Iuran bulanan blok');
        // Umum → wajib menunjuk satu jenis retribusi (100%).
        $retributionService->record($branchId, 'umum', 'Pedagang Kaki Lima Depan Pasar', null, 50000, 'tunai', $this->petugasUpf->id, 'Retribusi harian umum', retributionType: $sewaType);
    }

    private function createBusinessUnits(): void
    {
        if (BusinessUnit::query()->exists()) {
            return;
        }

        $unit1 = BusinessUnit::query()->create([
            'code' => 'UNIT-001',
            'name' => 'Sewa Aula Serbaguna',
            'coa_revenue_account_id' => ChartOfAccount::query()->where('code', '4301')->firstOrFail()->id,
            'coa_expense_account_id' => ChartOfAccount::query()->where('code', '5208')->firstOrFail()->id,
            'is_active' => true,
        ]);

        $unit2 = BusinessUnit::query()->create([
            'code' => 'UNIT-002',
            'name' => 'Fotokopi & ATK',
            'coa_revenue_account_id' => ChartOfAccount::query()->where('code', '4902')->firstOrFail()->id,
            'coa_expense_account_id' => ChartOfAccount::query()->where('code', '5206')->firstOrFail()->id,
            'is_active' => true,
        ]);

        $branchId = Branch::query()->where('name', 'Kantor Pusat')->value('id');
        $businessUnitService = app(BusinessUnitService::class);

        $businessUnitService->record($unit1, 'pendapatan', 750000, $branchId, $this->bendahara->id, 'Sewa aula untuk acara RT');
        $businessUnitService->record($unit1, 'beban', 100000, $branchId, $this->bendahara->id, 'Biaya kebersihan pasca acara');
        $businessUnitService->record($unit2, 'pendapatan', 250000, $branchId, $this->bendahara->id, 'Penjualan fotokopi & ATK harian');
    }

    /**
     * @param  array<int, Member>  $members
     */
    private function createCalendarEvents(array $members): void
    {
        if (CooperativeEvent::query()->exists()) {
            return;
        }

        $eventService = app(CooperativeEventService::class);

        $eventService->create([
            'title' => 'Rapat Anggota Tahunan (RAT) 2026',
            'description' => 'Pembahasan laporan pertanggungjawaban pengurus tahun buku 2025.',
            'start_at' => now()->addDays(20)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(20)->setTime(13, 0)->toDateTimeString(),
            'location' => 'Aula Kantor Pusat',
            'branch_scope_type' => 'all',
            'participant_type' => 'semua_anggota_cabang',
            'reminder_days' => [7, 1],
        ], $this->manajer->id);

        $eventService->create([
            'title' => 'Sosialisasi Produk Pinjaman Baru',
            'description' => 'Sosialisasi ke anggota terpilih mengenai produk pinjaman modal usaha.',
            'start_at' => now()->addDays(5)->setTime(10, 0)->toDateTimeString(),
            'end_at' => now()->addDays(5)->setTime(11, 30)->toDateTimeString(),
            'location' => 'Ruang Pertemuan Kantor Pusat',
            'branch_scope_type' => 'single',
            'single_branch_id' => Branch::query()->where('name', 'Kantor Pusat')->value('id'),
            'participant_type' => 'anggota_tertentu',
            'member_ids' => [$members[0]->id, $members[1]->id],
            'reminder_days' => [1],
        ], $this->manajer->id);

        $eventService->create([
            'title' => 'Penutupan Buku Bulanan (sudah lewat)',
            'start_at' => now()->subDays(3)->setTime(8, 0)->toDateTimeString(),
            'end_at' => now()->subDays(3)->setTime(17, 0)->toDateTimeString(),
            'location' => 'Kantor Pusat',
            'branch_scope_type' => 'all',
            'participant_type' => 'semua_anggota_cabang',
            'reminder_days' => [],
        ], $this->bendahara->id);
    }
}
