<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\LaporanController;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanSchedule;
use App\Models\Member;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceCoa;
use App\Models\OpeningBalanceLoan;
use App\Models\OpeningBalanceSaving;
use App\Models\SavingsProduct;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Reporting\LaporanRegistry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Laporan kelompok "Migrasi & Saldo Awal" — dibaca langsung dari tabel
 * opening_balance_*, bukan dari saldo berjalan. Setelah batch dikunci saldo
 * awal tidak bisa diubah, jadi satu-satunya cara memeriksanya adalah
 * membacanya kembali dari sumbernya sendiri.
 */
class LaporanMigrasiTest extends TestCase
{
    use RefreshDatabase;

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function ambil(string $module)
    {
        $controller = app(LaporanController::class);

        return (new ReflectionMethod($controller, 'fetch'))->invoke($controller, $module);
    }

    /** Tabel opening_balance_* belum punya factory — barisnya dibuat langsung. */
    private function batch(): OpeningBalanceBatch
    {
        return OpeningBalanceBatch::query()->create([
            'branch_id' => Branch::factory()->create()->id,
            'cutoff_date' => '2026-08-10',
            'status' => 'locked',
        ]);
    }

    /**
     * Tipe akun ikut ditentukan, bukan selalu ASET: Neraca mengelompokkan
     * Aktiva/Pasiva/Modal menurut tipe dan menjumlahkan sub totalnya secara
     * neto per kelompok, jadi fixture bertipe seragam akan menguji hal yang
     * salah.
     */
    private function akun(string $code, string $statement = 'NERACA', string $type = 'ASET'): ChartOfAccount
    {
        return ChartOfAccount::query()->create([
            'code' => $code, 'name' => 'Akun '.$code, 'type' => $type,
            'normal_balance' => $type === 'ASET' ? 'DEBIT' : 'KREDIT',
            'is_postable' => true, 'statement' => $statement,
        ]);
    }

    public function test_keenam_laporan_migrasi_terdaftar(): void
    {
        foreach ([
            'saldo_awal_neraca', 'saldo_awal_neraca_saldo', 'saldo_awal_pinjaman',
            'saldo_awal_simpanan', 'migrasi_historis_pembayaran', 'migrasi_jadwal_pembayaran',
        ] as $module) {
            $this->assertTrue(LaporanRegistry::exists($module), $module.' belum terdaftar');
        }

        $this->assertArrayHasKey('Migrasi & Saldo Awal', LaporanRegistry::groups());
    }

    /** Neraca hanya memuat akun Neraca; Neraca Saldo memuat seluruhnya. */
    public function test_neraca_menyaring_akun_laba_rugi_neraca_saldo_tidak(): void
    {
        $batch = $this->batch();

        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $this->akun('9001', 'NERACA')->id,
            'position' => 'debit', 'amount' => 1000,
        ]);
        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $this->akun('9002', 'LABA_RUGI')->id,
            'position' => 'kredit', 'amount' => 400,
        ]);

        $neraca = $this->ambil('saldo_awal_neraca');
        $neracaSaldo = $this->ambil('saldo_awal_neraca_saldo');

        // Akun Laba/Rugi tidak ditampilkan sendiri di Neraca, hanya diringkas
        // menjadi satu baris SHU berjalan.
        $this->assertNull($neraca->first(fn ($row) => $row['kode'] === '9002'));
        $this->assertNotNull($neraca->first(fn ($row) => $row['kode'] === '9001'));
        $this->assertNotNull($neraca->first(fn ($row) => str_contains($row['nama_akun'], 'SHU TAHUN BERJALAN')));
        $this->assertSame('TOTAL NERACA', $neraca->last()['nama_akun']);

        // Neraca Saldo: kedua akun ditampilkan apa adanya + TOTAL, tanpa SHU.
        $this->assertCount(3, $neracaSaldo);
        $this->assertNull($neracaSaldo->first(fn ($row) => str_contains($row['nama_akun'], 'SHU TAHUN BERJALAN')));
        $this->assertNotNull($neracaSaldo->first(fn ($row) => $row['kode'] === '9002'));
    }

    /**
     * Neraca harus seimbang setelah SHU berjalan dihitung dari akun Laba/Rugi.
     * Dari saldo mentah saja ia tidak akan pernah seimbang — hasil usaha masih
     * tersimpan di Pendapatan/Beban dan belum ditutup ke ekuitas.
     */
    public function test_neraca_seimbang_setelah_shu_berjalan_dihitung(): void
    {
        $batch = $this->batch();

        // Aktiva 1.000, pasiva 700, modal 200 → timpang 100 sebelum SHU dihitung.
        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $this->akun('1001', 'NERACA', 'ASET')->id,
            'position' => 'debit', 'amount' => 1000,
        ]);
        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $this->akun('2001', 'NERACA', 'LIABILITAS')->id,
            'position' => 'kredit', 'amount' => 700,
        ]);
        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $this->akun('3001', 'NERACA', 'EKUITAS')->id,
            'position' => 'kredit', 'amount' => 200,
        ]);

        // Pendapatan 300, beban 200 → SHU berjalan 100.
        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $this->akun('4001', 'LABA_RUGI', 'PENDAPATAN')->id,
            'position' => 'kredit', 'amount' => 300,
        ]);
        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $this->akun('5001', 'LABA_RUGI', 'BEBAN')->id,
            'position' => 'debit', 'amount' => 200,
        ]);

        $rows = $this->ambil('saldo_awal_neraca');

        $cari = fn (string $label) => $rows->first(fn ($row) => $row['nama_akun'] === $label);

        $this->assertSame('Rp 1.000', $cari('SUB TOTAL AKTIVA')['debit']);
        $this->assertSame('Rp 700', $cari('SUB TOTAL PASIVA')['kredit']);
        $this->assertSame('Rp 300', $cari('SUB TOTAL MODAL')['kredit']);
        $this->assertSame('Rp 1.000', $cari('TOTAL PASIVA + MODAL')['kredit']);

        $total = $rows->last();
        $this->assertSame('TOTAL NERACA', $total['nama_akun']);
        $this->assertSame('Rp 1.000', $total['debit']);
        $this->assertSame('Rp 1.000', $total['kredit']);

        $shu = $rows->first(fn ($row) => str_contains($row['nama_akun'], 'SHU TAHUN BERJALAN'));
        $this->assertSame('Rp 100', $shu['kredit']);
    }

    /**
     * Baris ringkasan ditandai supaya layar dan cetakan bisa memberinya latar
     * pembeda. Penandanya harus tidak terlihat sebagai kolom laporan — ia tidak
     * boleh ikut di Export Excel maupun jadi bahan pencarian.
     */
    public function test_baris_ringkasan_dan_judul_ditandai_gayanya(): void
    {
        $batch = $this->batch();

        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $this->akun('1001', 'NERACA', 'ASET')->id,
            'position' => 'debit', 'amount' => 1000,
        ]);

        $rows = $this->ambil('saldo_awal_neraca');

        $this->assertSame('judul', $rows->first(fn ($r) => $r['nama_akun'] === 'AKTIVA')['_gaya']);
        $this->assertSame('ringkasan', $rows->first(fn ($r) => $r['nama_akun'] === 'SUB TOTAL AKTIVA')['_gaya']);
        $this->assertSame('ringkasan', $rows->last()['_gaya']);

        // Baris akun biasa tidak ditandai.
        $this->assertArrayNotHasKey('_gaya', $rows->first(fn ($r) => $r['kode'] === '1001'));

        // Penanda bukan kolom laporan, jadi tidak muncul di daftar kolom.
        $this->assertArrayNotHasKey('_gaya', LaporanRegistry::columnsFor('saldo_awal_neraca'));
    }

    /** Penanda gaya tidak boleh ikut jadi bahan pencarian. */
    public function test_penanda_gaya_tidak_ikut_dicari(): void
    {
        $controller = app(LaporanController::class);
        $saring = new \ReflectionMethod($controller, 'saringSepertiDiLayar');

        $rows = collect([
            ['member_number' => 'A-001', 'name' => 'Budi', 'cabang' => 'KSP', 'joined_at' => '10-01-2026'],
            ['member_number' => '', 'name' => 'TOTAL', 'cabang' => '', 'joined_at' => '', '_gaya' => 'ringkasan'],
        ]);

        $hasil = $saring->invoke($controller, $rows, \Illuminate\Http\Request::create('/', 'GET', ['cari' => 'ringkasan']), 'anggota');

        $this->assertCount(0, $hasil);
    }

    /** Aktiva harus mendahului Pasiva dan Modal, apa pun urutan datanya. */
    public function test_urutan_bagian_aktiva_pasiva_modal(): void
    {
        $batch = $this->batch();

        // Sengaja dimasukkan terbalik: ekuitas dulu, aset terakhir.
        foreach ([['3001', 'EKUITAS', 'kredit'], ['2001', 'LIABILITAS', 'kredit'], ['1001', 'ASET', 'debit']] as [$kode, $tipe, $posisi]) {
            OpeningBalanceCoa::query()->create([
                'opening_balance_batch_id' => $batch->id,
                'chart_of_account_id' => $this->akun($kode, 'NERACA', $tipe)->id,
                'position' => $posisi, 'amount' => 100,
            ]);
        }

        $labels = $this->ambil('saldo_awal_neraca')->pluck('nama_akun')->all();

        $this->assertLessThan(array_search('PASIVA (KEWAJIBAN)', $labels, true), array_search('AKTIVA', $labels, true));
        $this->assertLessThan(array_search('MODAL (EKUITAS)', $labels, true), array_search('PASIVA (KEWAJIBAN)', $labels, true));
    }

    /** Neraca Saldo sudah memuat akun Laba/Rugi — SHU tidak boleh ditambahkan lagi. */
    public function test_neraca_saldo_tidak_menambahkan_baris_shu(): void
    {
        $batch = $this->batch();

        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $this->akun('9101', 'LABA_RUGI')->id,
            'position' => 'kredit', 'amount' => 300,
        ]);

        $rows = $this->ambil('saldo_awal_neraca_saldo');

        $this->assertNull($rows->first(fn ($row) => str_contains($row['nama_akun'], 'SHU TAHUN BERJALAN')));
        $this->assertSame('Rp 300', $rows->last()['kredit']);
    }

    /** Baris TOTAL adalah alasan utama laporan ini dibuka saat pengecekan. */
    public function test_baris_total_menjumlahkan_debit_dan_kredit(): void
    {
        $batch = $this->batch();

        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $this->akun('1001', 'NERACA', 'ASET')->id,
            'position' => 'debit', 'amount' => 1500,
        ]);
        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $this->akun('2001', 'NERACA', 'LIABILITAS')->id,
            'position' => 'kredit', 'amount' => 900,
        ]);

        $total = $this->ambil('saldo_awal_neraca')->last();

        $this->assertSame('TOTAL NERACA', $total['nama_akun']);
        $this->assertSame('Rp 1.500', $total['debit']);
        $this->assertSame('Rp 900', $total['kredit']);
    }

    /**
     * Akun lawan seperti akumulasi penyusutan bersaldo kredit tapi tetap masuk
     * kelompok Aktiva. Sub totalnya harus neto — dijumlah per kolom, Total
     * Aktiva akan tampak lebih besar dari yang sebenarnya.
     */
    public function test_sub_total_aktiva_dihitung_neto_terhadap_akun_lawan(): void
    {
        $batch = $this->batch();

        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $this->akun('1001', 'NERACA', 'ASET')->id,
            'position' => 'debit', 'amount' => 1000,
        ]);
        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $this->akun('1204101', 'NERACA', 'ASET')->id,
            'position' => 'kredit', 'amount' => 250,
        ]);

        $rows = $this->ambil('saldo_awal_neraca');
        $subTotal = $rows->first(fn ($row) => $row['nama_akun'] === 'SUB TOTAL AKTIVA');

        $this->assertSame('Rp 750', $subTotal['debit']);
    }

    public function test_saldo_awal_pinjaman_memecah_pokok_dan_jasa(): void
    {
        $batch = $this->batch();
        $member = Member::factory()->create(['member_number' => 'A-001', 'name' => 'Budi']);

        OpeningBalanceLoan::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'member_id' => $member->id,
            'loan_product_id' => LoanProduct::factory()->create()->id,
            'external_loan_number' => '117-0151-00045',
            'disbursement_date' => '2026-05-26',
            'original_principal' => 3000000,
            'outstanding_principal' => 871000,
            'outstanding_interest' => 36300,
            'tenor_months' => 3,
            'remaining_tenor_months' => 1,
            'next_installment_number' => 3,
            'next_due_date' => '2026-08-26',
            'collectibility' => 'lancar',
        ]);

        $row = $this->ambil('saldo_awal_pinjaman')->first();

        $this->assertSame('A-001', $row['kode_anggota']);
        $this->assertSame('117-0151-00045', $row['no_pinjaman_lama']);
        $this->assertSame('Rp 871.000', $row['sisa_pokok']);
        $this->assertSame('Rp 36.300', $row['sisa_jasa']);
    }

    /**
     * Sub total muncul hanya untuk anggota berbaris ganda. Pada anggota dengan
     * satu pinjaman, sub total hanya akan menggandakan angka yang sama tepat di
     * bawahnya — menambah panjang cetakan tanpa menambah informasi.
     */
    public function test_sub_total_pinjaman_hanya_untuk_anggota_berpinjaman_ganda(): void
    {
        $batch = $this->batch();
        $produk = LoanProduct::factory()->create();
        $ganda = Member::factory()->create(['member_number' => 'A-001', 'name' => 'Budi']);
        $tunggal = Member::factory()->create(['member_number' => 'A-002', 'name' => 'Siti']);

        $buat = function (Member $member, string $nomor, float $pokok) use ($batch, $produk) {
            OpeningBalanceLoan::query()->create([
                'opening_balance_batch_id' => $batch->id,
                'member_id' => $member->id,
                'loan_product_id' => $produk->id,
                'external_loan_number' => $nomor,
                'disbursement_date' => '2026-05-26',
                'original_principal' => $pokok,
                'outstanding_principal' => $pokok,
                'outstanding_interest' => 0,
                'tenor_months' => 3, 'remaining_tenor_months' => 1,
                'next_installment_number' => 3, 'next_due_date' => '2026-08-26',
                'collectibility' => 'lancar',
            ]);
        };

        $buat($ganda, 'L-001', 100000);
        $buat($ganda, 'L-002', 200000);
        $buat($tunggal, 'L-003', 50000);

        $rows = $this->ambil('saldo_awal_pinjaman');
        $label = $rows->pluck('nama_anggota')->all();

        $this->assertContains('SUB TOTAL Budi', $label);
        $this->assertNotContains('SUB TOTAL Siti', $label);

        $subTotal = $rows->first(fn ($row) => $row['nama_anggota'] === 'SUB TOTAL Budi');
        $this->assertSame('Rp 300.000', $subTotal['sisa_pokok']);
        $this->assertSame('2 pinjaman', $subTotal['no_pinjaman_lama']);

        $total = $rows->last();
        $this->assertSame('TOTAL SELURUH ANGGOTA', $total['nama_anggota']);
        $this->assertSame('Rp 350.000', $total['sisa_pokok']);
    }

    public function test_saldo_awal_simpanan_menampilkan_saldo_per_rekening(): void
    {
        $batch = $this->batch();
        $member = Member::factory()->create(['member_number' => 'A-002', 'name' => 'Siti']);

        OpeningBalanceSaving::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'member_id' => $member->id,
            'savings_product_id' => SavingsProduct::factory()->create()->id,
            'account_number' => '117-0101-00001',
            'balance' => 25000,
            'notes' => 'Migrasi SMIK',
        ]);

        $row = $this->ambil('saldo_awal_simpanan')->first();

        $this->assertSame('A-002', $row['kode_anggota']);
        $this->assertSame('Rp 25.000', $row['saldo_awal']);
        $this->assertSame('Migrasi SMIK', $row['keterangan']);
    }

    public function test_jadwal_pembayaran_menghitung_sisa_per_angsuran(): void
    {
        $loan = Loan::factory()->create(['loan_number' => 'L-001']);

        LoanSchedule::query()->create([
            'loan_id' => $loan->id, 'installment_number' => 1, 'due_date' => '2026-09-10',
            'principal_amount' => 350000, 'interest_amount' => 49700, 'total_amount' => 399700,
            'paid_amount' => 100000, 'status' => 'sebagian',
        ]);

        $row = $this->ambil('migrasi_jadwal_pembayaran')->first();

        $this->assertSame('L-001', $row['no_pinjaman']);
        $this->assertSame('Rp 299.700', $row['sisa']);
        $this->assertSame('Sebagian', $row['status']);
    }

    /** Historis pembayaran masih kosong sampai importirnya dibangun. */
    public function test_historis_pembayaran_kosong_tanpa_error(): void
    {
        $this->assertCount(0, $this->ambil('migrasi_historis_pembayaran'));
    }

    public function test_halaman_laporan_migrasi_terbuka(): void
    {
        $this->actingAs($this->manajer())
            ->get(route('admin.laporan.show', 'saldo_awal_neraca'))
            ->assertOk()
            ->assertSee('Saldo Awal');
    }
}
