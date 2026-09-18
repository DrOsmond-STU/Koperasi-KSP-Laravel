<?php

namespace Tests\Feature\Reporting;

use App\Exports\GenericListExport;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Reporting\LaporanRegistry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Baris TOTAL pada hub Laporan, dari sisi halaman yang benar-benar dibuka
 * pengguna.
 *
 * Permintaannya: "tambahkan total angka semua kolom sesuai dengan data filter
 * yang telah di tetapkan". Kata kuncinya SESUAI FILTER — dan itulah yang sulit,
 * karena saringan datatable bekerja di browser, bukan di server. Pembagian
 * tugasnya: server menetapkan kolom mana yang punya total (dititipkan lewat
 * data-sum-columns), browser menjumlahkan baris yang sedang terlihat.
 *
 * Yang bisa diuji di sini adalah separuh milik server — kolom mana yang
 * ditawarkan, dan bahwa cetakannya memuat totalnya. Penjumlahan di browser
 * memakai aturan yang kembar persis dan diuji di PenjumlahLaporanTest.
 */
class LaporanTotalTest extends TestCase
{
    use RefreshDatabase;

    private function pengurus(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    /**
     * Dibuat langsung, bukan lewat factory: LoanRepayment tidak punya factory,
     * dan LoanRepaymentService menolak angsuran tanpa jadwal — padahal yang
     * diuji di sini hanya bagaimana angkanya ditampilkan.
     */
    private function angsuran(float $jumlah, float $pokok, float $jasa, float $sisa): LoanRepayment
    {
        $loan = Loan::factory()->create(['status' => 'dicairkan']);

        return LoanRepayment::query()->create([
            'branch_id' => $loan->branch_id,
            'loan_id' => $loan->id,
            'amount' => $jumlah,
            'principal_portion' => $pokok,
            'interest_portion' => $jasa,
            'balance_after' => $sisa,
            'created_by' => User::factory()->create()->id,
        ]);
    }

    public function test_layar_menawarkan_total_untuk_kolom_nominal(): void
    {
        $this->angsuran(300000, 200000, 100000, 2700000);

        $response = $this->actingAs($this->pengurus())
            ->get(route('admin.laporan.show', 'pembayaran_angsuran'));

        $response->assertOk();
        $response->assertSee('data-sum-columns="jumlah,pokok,jasa,denda"', false);
        $response->assertSee('data-total-column="jumlah"', false);
    }

    /**
     * Inti dari daftar pengecualian. Sisa Pinjaman adalah saldo berjalan:
     * menjumlahkan 7.745 baris saldo menghasilkan angka bermiliar-miliar yang
     * terlihat resmi dan sama sekali tidak berarti.
     */
    public function test_sisa_pinjaman_tidak_ditawarkan_untuk_dijumlah(): void
    {
        $this->angsuran(300000, 200000, 100000, 2700000);

        $response = $this->actingAs($this->pengurus())
            ->get(route('admin.laporan.show', 'pembayaran_angsuran'));

        $response->assertOk();
        $response->assertDontSee('data-total-column="saldo_akhir"', false);
    }

    public function test_laporan_tanpa_kolom_nominal_tidak_menampilkan_kaki_total(): void
    {
        $response = $this->actingAs($this->pengurus())
            ->get(route('admin.laporan.show', 'anggota'));

        $response->assertOk();
        // Dicari bentuk ATRIBUT-nya, bukan sekadar namanya: skrip datatable
        // bersama di layouts/app.blade.php menyebut "data-sum-columns" di
        // komentarnya, dan komentar itu ikut terkirim ke setiap halaman.
        $response->assertDontSee('data-sum-columns="', false);
        $response->assertDontSee('<tfoot>', false);
    }

    public function test_export_excel_memuat_baris_total_di_kaki_berkas(): void
    {
        $this->angsuran(300000, 200000, 100000, 2700000);
        $this->angsuran(150000, 100000, 50000, 2550000);

        Excel::fake();

        $this->actingAs($this->pengurus())
            ->get(route('admin.laporan.export-excel', 'pembayaran_angsuran'))
            ->assertOk();

        Excel::assertDownloaded('pembayaran-angsuran-'.now()->format('Ymd-His').'.xlsx',
            function (GenericListExport $export) {
                $baris = $export->collection();
                $total = $baris->last();
                $kolom = array_keys(LaporanRegistry::columnsFor('pembayaran_angsuran'));

                // Dua angsuran + satu baris total.
                $this->assertCount(3, $baris);
                $this->assertSame('TOTAL', $total[array_search('tanggal', $kolom, true)]);
                $this->assertSame('Rp 450.000', $total[array_search('jumlah', $kolom, true)]);
                $this->assertSame('Rp 300.000', $total[array_search('pokok', $kolom, true)]);
                $this->assertSame('Rp 150.000', $total[array_search('jasa', $kolom, true)]);

                // Saldo berjalan tetap kosong, bukan dijumlah jadi Rp 5.250.000.
                $this->assertSame('', $total[array_search('saldo_akhir', $kolom, true)]);

                return true;
            });
    }

    /**
     * Kaki tabel pada cetakan PDF. Blade-nya dirender langsung, bukan lewat
     * DomPDF: isi berkas PDF terkompresi dan tidak bisa dicari sebagai teks,
     * padahal yang perlu dikunci justru tata letaknya.
     */
    public function test_cetakan_pdf_memuat_baris_total_di_kaki_tabel(): void
    {
        $html = view('prints.laporan.generic', [
            'title' => 'Transaksi Pembayaran Angsuran',
            'columns' => ['tanggal' => 'Tanggal', 'jumlah' => 'Jumlah Bayar'],
            'rows' => collect([
                ['tanggal' => '01-09-2026', 'jumlah' => 'Rp 300.000'],
                ['tanggal' => '02-09-2026', 'jumlah' => 'Rp 150.000'],
            ]),
            'total' => ['tanggal' => 'TOTAL', 'jumlah' => 'Rp 450.000'],
            'generatedAt' => now(),
            'catatan' => null,
        ])->render();

        $this->assertStringContainsString('<tfoot>', $html);
        $this->assertStringContainsString('Rp 450.000', $html);
    }

    /**
     * Cetakan dibuat dari baris yang SUDAH tersaring, jadi totalnya ikut
     * menyempit — tanpa itu, angka di kertas akan membantah angka di layar
     * yang baru saja dilihat pengguna.
     */
    public function test_export_excel_menjumlah_hanya_baris_yang_lolos_saringan(): void
    {
        $lolos = $this->angsuran(300000, 200000, 100000, 2700000);
        $this->angsuran(150000, 100000, 50000, 2550000);

        Excel::fake();

        $this->actingAs($this->pengurus())
            ->get(route('admin.laporan.export-excel', 'pembayaran_angsuran').'?cari='.$lolos->loan->loan_number)
            ->assertOk();

        Excel::assertDownloaded('pembayaran-angsuran-'.now()->format('Ymd-His').'.xlsx',
            function (GenericListExport $export) {
                $baris = $export->collection();
                $kolom = array_keys(LaporanRegistry::columnsFor('pembayaran_angsuran'));

                // Satu angsuran yang cocok + satu baris total.
                $this->assertCount(2, $baris);
                $this->assertSame('Rp 300.000', $baris->last()[array_search('jumlah', $kolom, true)]);

                return true;
            });
    }
}
