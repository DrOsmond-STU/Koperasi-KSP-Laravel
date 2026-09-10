<?php

namespace Tests\Feature\OpeningBalance;

use App\Models\Branch;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceLoan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

/**
 * Perintah saldo-awal:koreksi-pinjaman — menyelaraskan opening_balance_loans
 * dengan berkas daftar pinjaman yang sudah cocok dengan neraca.
 */
class KoreksiSaldoAwalPinjamanTest extends TestCase
{
    use RefreshDatabase;

    private Branch $cabang;

    private LoanProduct $produk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cabang = Branch::factory()->create();
        $this->produk = LoanProduct::factory()->create(['code' => 'PH']);
    }

    private function batch(string $status = 'draft'): OpeningBalanceBatch
    {
        return OpeningBalanceBatch::query()->create([
            'branch_id' => $this->cabang->id,
            'cutoff_date' => '2026-07-31',
            'status' => $status,
        ]);
    }

    private function anggota(string $nomor, string $nama): Member
    {
        return Member::factory()->create(['member_number' => $nomor, 'name' => $nama]);
    }

    private function barisSaldoAwal(OpeningBalanceBatch $batch, Member $anggota, array $ubah = []): OpeningBalanceLoan
    {
        return OpeningBalanceLoan::query()->create(array_merge([
            'opening_balance_batch_id' => $batch->id,
            'member_id' => $anggota->id,
            'loan_product_id' => $this->produk->id,
            'disbursement_date' => '2026-05-21',
            'original_principal' => 3000000,
            'outstanding_principal' => 999999,
            'outstanding_interest' => 111111,
            'tenor_months' => 3,
            'remaining_tenor_months' => 2,
            'next_installment_number' => 1,
            'next_due_date' => '2026-08-29',
            'collectibility' => 'lancar',
        ], $ubah));
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: float, 4: float, 5: float, 6: float, 7: string, 8: int}>  $baris
     */
    private function berkas(array $baris): string
    {
        $buku = new Spreadsheet;
        $lembar = $buku->getActiveSheet();
        $lembar->fromArray([
            'Tanggal  Pinjaman', 'Nomor anggota', 'Nama Anggota', 'Jumlah Pinjaman',
            'Jasa Pinjaman', 'Sisa Pinjaman', 'Sisa Jasa', 'Jatuh Tempo',
            'Jangka Waktu Pinjaman dalam hari',
        ], null, 'A1');

        $nomorBaris = 2;
        foreach ($baris as $b) {
            $lembar->fromArray($b, null, 'A'.$nomorBaris);
            $nomorBaris++;
        }

        $path = tempnam(sys_get_temp_dir(), 'daftar_pinjaman_').'.xlsx';
        (new XlsxWriter($buku))->save($path);

        return $path;
    }

    public function test_uji_kering_melaporkan_selisih_tanpa_mengubah_data(): void
    {
        $batch = $this->batch();
        $anggota = $this->anggota('1170100004', 'Maman Sayur');
        $baris = $this->barisSaldoAwal($batch, $anggota);

        $berkas = $this->berkas([
            ['2026-05-21', '1170100004', 'Maman Sayur', 3000000, 150000, 1074500, 52500, '2026-08-29', 100],
        ]);

        $this->artisan("saldo-awal:koreksi-pinjaman {$batch->id} {$berkas}")
            ->assertSuccessful();

        $baris->refresh();
        $this->assertSame('999999.00', $baris->outstanding_principal);
        $this->assertSame('111111.00', $baris->outstanding_interest);
    }

    public function test_terapkan_menulis_sisa_pokok_dan_sisa_jasa_dari_berkas(): void
    {
        $batch = $this->batch();
        $anggota = $this->anggota('1170100004', 'Maman Sayur');
        $baris = $this->barisSaldoAwal($batch, $anggota);

        $berkas = $this->berkas([
            ['2026-05-21', '1170100004', 'Maman Sayur', 3000000, 150000, 1074500, 52500, '2026-08-29', 100],
        ]);

        $this->artisan("saldo-awal:koreksi-pinjaman {$batch->id} {$berkas} --terapkan")
            ->assertSuccessful();

        $baris->refresh();
        $this->assertSame('1074500.00', $baris->outstanding_principal);
        $this->assertSame('52500.00', $baris->outstanding_interest);
        $this->assertSame('3000000.00', $baris->original_principal);
    }

    /**
     * Kolom yang tidak punya sumber data di berkas tidak boleh ikut berubah —
     * kolektibilitas dan sisa tenor selama ini benar dan tidak ada di berkas.
     */
    public function test_kolom_tanpa_sumber_data_dibiarkan_apa_adanya(): void
    {
        $batch = $this->batch();
        $anggota = $this->anggota('1170100004', 'Maman Sayur');
        $baris = $this->barisSaldoAwal($batch, $anggota, [
            'collectibility' => 'macet',
            'remaining_tenor_months' => 2,
            'next_installment_number' => 5,
            'external_loan_number' => 'LAMA-77',
            // Sengaja dibuat berbeda dari "Jatuh Tempo" di berkas (2026-08-29),
            // supaya terlihat kalau kolom ini sampai ikut tertimpa.
            'next_due_date' => '2026-06-30',
        ]);

        $berkas = $this->berkas([
            ['2026-05-21', '1170100004', 'Maman Sayur', 3000000, 150000, 1074500, 52500, '2026-08-29', 100],
        ]);

        $this->artisan("saldo-awal:koreksi-pinjaman {$batch->id} {$berkas} --terapkan")
            ->assertSuccessful();

        $baris->refresh();
        $this->assertSame('macet', $baris->collectibility);
        $this->assertSame(2, $baris->remaining_tenor_months);
        $this->assertSame(5, $baris->next_installment_number);
        $this->assertSame('LAMA-77', $baris->external_loan_number);
        // next_due_date menyimpan jatuh tempo angsuran berikutnya, bukan akhir
        // masa pinjaman seperti kolom "Jatuh Tempo" di berkas — jadi ia harus
        // tetap 30 Juni, bukan berubah jadi 29 Agustus mengikuti berkas.
        $this->assertSame('2026-06-30', $baris->next_due_date->toDateString());
    }

    /**
     * Kolom tenor punya satuan yang berbeda antar produk dan tabel saldo awal
     * belum bisa menyatakannya, jadi bawaannya tidak disentuh sama sekali.
     */
    public function test_tenor_tidak_disentuh_secara_bawaan(): void
    {
        $batch = $this->batch();
        $anggota = $this->anggota('1170100004', 'Maman Sayur');
        $baris = $this->barisSaldoAwal($batch, $anggota);

        $berkas = $this->berkas([
            ['2026-05-21', '1170100004', 'Maman Sayur', 3000000, 150000, 1074500, 52500, '2026-08-29', 200],
        ]);

        $this->artisan("saldo-awal:koreksi-pinjaman {$batch->id} {$berkas} --terapkan")
            ->assertSuccessful();

        $this->assertSame(3, $baris->refresh()->tenor_months);
    }

    public function test_opsi_tenor_hari_menyalin_jangka_waktu_apa_adanya(): void
    {
        $batch = $this->batch();
        $anggota = $this->anggota('1170100004', 'Maman Sayur');
        $baris = $this->barisSaldoAwal($batch, $anggota);

        $berkas = $this->berkas([
            ['2026-05-21', '1170100004', 'Maman Sayur', 3000000, 150000, 1074500, 52500, '2026-08-29', 200],
        ]);

        $this->artisan("saldo-awal:koreksi-pinjaman {$batch->id} {$berkas} --terapkan --tenor=hari")
            ->assertSuccessful();

        $this->assertSame(200, $baris->refresh()->tenor_months);
    }

    /**
     * Satu anggota dapat memiliki lebih dari satu pinjaman; pencocokan harus
     * memakai nomor anggota + tanggal akad supaya keduanya tidak saling
     * menimpa.
     */
    public function test_dua_pinjaman_satu_anggota_dicocokkan_per_tanggal_akad(): void
    {
        $batch = $this->batch();
        $anggota = $this->anggota('1170100004', 'Maman Sayur');
        $pertama = $this->barisSaldoAwal($batch, $anggota, ['disbursement_date' => '2026-05-21']);
        $kedua = $this->barisSaldoAwal($batch, $anggota, ['disbursement_date' => '2026-06-11']);

        $berkas = $this->berkas([
            ['2026-05-21', '1170100004', 'Maman Sayur', 3000000, 150000, 1074500, 52500, '2026-08-29', 100],
            ['2026-06-11', '1170100004', 'Maman Sayur', 5000000, 250000, 2957500, 142500, '2026-09-19', 100],
        ]);

        $this->artisan("saldo-awal:koreksi-pinjaman {$batch->id} {$berkas} --terapkan")
            ->assertSuccessful();

        $this->assertSame('1074500.00', $pertama->refresh()->outstanding_principal);
        $this->assertSame('2957500.00', $kedua->refresh()->outstanding_principal);
    }

    public function test_anggota_tidak_dikenal_membatalkan_seluruh_penerapan(): void
    {
        $batch = $this->batch();
        $anggota = $this->anggota('1170100004', 'Maman Sayur');
        $baris = $this->barisSaldoAwal($batch, $anggota);

        $berkas = $this->berkas([
            ['2026-05-21', '1170100004', 'Maman Sayur', 3000000, 150000, 1074500, 52500, '2026-08-29', 100],
            ['2026-05-21', '9999999999', 'Tidak Terdaftar', 1000000, 50000, 500000, 25000, '2026-08-29', 100],
        ]);

        $this->artisan("saldo-awal:koreksi-pinjaman {$batch->id} {$berkas} --terapkan")
            ->assertFailed();

        // Baris yang sah pun tidak ditulis — galat menghentikan seluruh proses.
        $this->assertSame('999999.00', $baris->refresh()->outstanding_principal);
    }

    /**
     * Tanggal akad sesudah cutoff mustahil ada di saldo awal — biasanya hari
     * dan bulannya tertukar. Ditolak supaya tidak diam-diam tertulis.
     */
    public function test_tanggal_pinjaman_melewati_cutoff_ditolak(): void
    {
        $batch = $this->batch();
        $anggota = $this->anggota('1170100236', 'Susana');
        $baris = $this->barisSaldoAwal($batch, $anggota);

        $berkas = $this->berkas([
            ['2026-12-05', '1170100236', 'Susana', 10000000, 1000000, 3900000, 390000, '2026-08-27', -100],
        ]);

        $this->artisan("saldo-awal:koreksi-pinjaman {$batch->id} {$berkas} --terapkan")
            ->assertFailed();

        $this->assertSame('999999.00', $baris->refresh()->outstanding_principal);
    }

    public function test_batch_terkunci_ditolak_tanpa_paksa(): void
    {
        $batch = $this->batch('locked');
        $anggota = $this->anggota('1170100004', 'Maman Sayur');
        $baris = $this->barisSaldoAwal($batch, $anggota);

        $berkas = $this->berkas([
            ['2026-05-21', '1170100004', 'Maman Sayur', 3000000, 150000, 1074500, 52500, '2026-08-29', 100],
        ]);

        $this->artisan("saldo-awal:koreksi-pinjaman {$batch->id} {$berkas} --terapkan")
            ->assertFailed();

        $this->assertSame('999999.00', $baris->refresh()->outstanding_principal);
    }

    public function test_baris_basis_data_di_luar_berkas_hanya_dihapus_bila_diminta(): void
    {
        $batch = $this->batch();
        $tetap = $this->anggota('1170100004', 'Maman Sayur');
        $lepas = $this->anggota('1170100006', 'Selamet Raharjo');
        $this->barisSaldoAwal($batch, $tetap);
        $barisLepas = $this->barisSaldoAwal($batch, $lepas);

        $berkas = $this->berkas([
            ['2026-05-21', '1170100004', 'Maman Sayur', 3000000, 150000, 1074500, 52500, '2026-08-29', 100],
        ]);

        $this->artisan("saldo-awal:koreksi-pinjaman {$batch->id} {$berkas} --terapkan")
            ->assertSuccessful();
        $this->assertDatabaseHas('opening_balance_loans', ['id' => $barisLepas->id]);

        $this->artisan("saldo-awal:koreksi-pinjaman {$batch->id} {$berkas} --terapkan --hapus-selisih")
            ->assertSuccessful();
        $this->assertDatabaseMissing('opening_balance_loans', ['id' => $barisLepas->id]);
    }

    public function test_baris_baru_ditambahkan_bila_produk_disebutkan(): void
    {
        $batch = $this->batch();
        $anggota = $this->anggota('1170100004', 'Maman Sayur');

        $berkas = $this->berkas([
            ['2026-05-21', '1170100004', 'Maman Sayur', 3000000, 150000, 1074500, 52500, '2026-08-29', 100],
        ]);

        // Tanpa --produk baris baru dianggap galat, bukan diam-diam ditebak.
        $this->artisan("saldo-awal:koreksi-pinjaman {$batch->id} {$berkas} --terapkan")
            ->assertFailed();
        $this->assertDatabaseCount('opening_balance_loans', 0);

        $this->artisan("saldo-awal:koreksi-pinjaman {$batch->id} {$berkas} --terapkan --produk=PH")
            ->assertSuccessful();
        $this->assertDatabaseHas('opening_balance_loans', [
            'member_id' => $anggota->id,
            'outstanding_principal' => 1074500,
            'outstanding_interest' => 52500,
            'tenor_months' => 100,
            'remaining_tenor_months' => 100,
        ]);
    }

    public function test_laporan_csv_dihasilkan_walau_uji_kering(): void
    {
        $batch = $this->batch();
        $anggota = $this->anggota('1170100004', 'Maman Sayur');
        $this->barisSaldoAwal($batch, $anggota);

        $berkas = $this->berkas([
            ['2026-05-21', '1170100004', 'Maman Sayur', 3000000, 150000, 1074500, 52500, '2026-08-29', 100],
        ]);

        $keluaran = sys_get_temp_dir().'/laporan-uji-'.uniqid();

        $this->artisan("saldo-awal:koreksi-pinjaman {$batch->id} {$berkas} --keluaran={$keluaran}")
            ->assertSuccessful();

        $dir = glob($keluaran.'/koreksi-saldo-awal-batch*')[0] ?? null;
        $this->assertNotNull($dir, 'Direktori laporan tidak dibuat.');

        foreach (['perubahan.csv', 'ditambah.csv', 'tidak-ada-di-berkas.csv', 'galat.csv', 'tindak-lanjut-angsuran.csv'] as $nama) {
            $this->assertFileExists($dir.'/'.$nama);
        }

        $isi = file_get_contents($dir.'/perubahan.csv');
        $this->assertStringContainsString('outstanding_principal', $isi);
        $this->assertStringContainsString('1074500.00', $isi);
    }
}
