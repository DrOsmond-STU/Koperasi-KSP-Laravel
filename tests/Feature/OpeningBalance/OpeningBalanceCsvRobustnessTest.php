<?php

namespace Tests\Feature\OpeningBalance;

use App\Models\Branch;
use App\Models\Member;
use App\Models\OpeningBalanceBatch;
use App\Models\SavingsProduct;
use App\Services\OpeningBalance\OpeningBalanceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ketahanan pembacaan CSV Saldo Awal.
 *
 * Regresi nyata: file simpanan hasil sunting Excel membuat import membalas
 * 500 Server Error. Penyebabnya berlapis — readCsv() tidak membuang BOM UTF-8
 * dan memaku delimiter koma, sehingga kolom "kode_anggota" tidak pernah
 * terbentuk; lalu pesan kesalahannya sendiri mengakses $row['kode_anggota']
 * tanpa pengaman, jadi masalah data berubah jadi ErrorException.
 *
 * File yang cacat harus selalu menghasilkan pesan per baris, tidak pernah 500.
 */
class OpeningBalanceCsvRobustnessTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'kode_anggota,kode_produk_simpanan,no_rekening,saldo_awal,tanggal_saldo_awal,keterangan';

    private function batch(): OpeningBalanceBatch
    {
        return OpeningBalanceBatch::query()->create([
            'branch_id' => Branch::factory()->create()->id,
            'cutoff_date' => '2026-01-01',
            'status' => 'draft',
        ]);
    }

    private function csvPath(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ob_csv_').'.csv';
        file_put_contents($path, $content);

        return $path;
    }

    private function service(): OpeningBalanceImportService
    {
        return app(OpeningBalanceImportService::class);
    }

    /** Excel "Save as CSV UTF-8" menambahkan BOM di depan kolom pertama. */
    public function test_bom_utf8_di_awal_file_tidak_merusak_kolom_pertama(): void
    {
        Member::factory()->create(['member_number' => 'AGT-0001']);
        SavingsProduct::factory()->create(['code' => 'SIM-01']);

        $csv = "\xEF\xBB\xBF".self::HEADER."\n".'AGT-0001,SIM-01,,1500000.00,2026-01-01,Migrasi'."\n";

        $result = $this->service()->validateSavings($this->batch(), $this->csvPath($csv));

        $this->assertFalse($result->hasErrors(), 'BOM seharusnya dibuang, bukan merusak nama kolom pertama');
        $this->assertCount(1, $result->validRows);
        $this->assertEquals(1500000.0, $result->validRows[0]['data']['balance']);
    }

    /** Excel pada Windows berlokal Indonesia menyimpan CSV dengan titik koma. */
    public function test_delimiter_titik_koma_terbaca(): void
    {
        Member::factory()->create(['member_number' => 'AGT-0001']);
        SavingsProduct::factory()->create(['code' => 'SIM-01']);

        $csv = str_replace(',', ';', self::HEADER)."\n".'AGT-0001;SIM-01;;1500000.00;2026-01-01;Migrasi'."\n";

        $result = $this->service()->validateSavings($this->batch(), $this->csvPath($csv));

        $this->assertFalse($result->hasErrors(), 'delimiter titik koma harus terdeteksi otomatis');
        $this->assertCount(1, $result->validRows);
    }

    /** BOM + titik koma sekaligus — kombinasi paling lazim dari Excel. */
    public function test_bom_dan_titik_koma_sekaligus(): void
    {
        Member::factory()->create(['member_number' => 'AGT-0001']);
        SavingsProduct::factory()->create(['code' => 'SIM-01']);

        $csv = "\xEF\xBB\xBF".str_replace(',', ';', self::HEADER)."\n".'AGT-0001;SIM-01;;250000;2026-01-01;'."\n";

        $result = $this->service()->validateSavings($this->batch(), $this->csvPath($csv));

        $this->assertFalse($result->hasErrors());
        $this->assertEquals(250000.0, $result->validRows[0]['data']['balance']);
    }

    /**
     * Inti perbaikan: header yang sama sekali tidak dikenal harus dilaporkan
     * sebagai error per baris, BUKAN melempar ErrorException (500).
     */
    public function test_header_tidak_dikenal_menghasilkan_pesan_bukan_error_500(): void
    {
        $csv = "kolom_ngawur,kolom_lain\n".'isi,isi lain'."\n";

        $result = $this->service()->validateSavings($this->batch(), $this->csvPath($csv));

        $this->assertTrue($result->hasErrors());
        $this->assertCount(0, $result->validRows);

        $pesan = implode(' | ', $result->errors[0]['errors']);
        $this->assertStringContainsString('kode_anggota', $pesan);
        $this->assertStringContainsString('tidak ditemukan', $pesan);
    }

    /** Baris dengan jumlah kolom lebih sedikit dari header tidak boleh fatal. */
    public function test_baris_kurang_kolom_tidak_menggagalkan_seluruh_import(): void
    {
        Member::factory()->create(['member_number' => 'AGT-0001']);
        SavingsProduct::factory()->create(['code' => 'SIM-01']);

        $csv = self::HEADER."\n"
            .'AGT-0001,SIM-01,,1000,2026-01-01,Baris lengkap'."\n"
            .'AGT-0001,SIM-01'."\n";

        $result = $this->service()->validateSavings($this->batch(), $this->csvPath($csv));

        $this->assertSame(2, $result->totalRows());
        $this->assertCount(1, $result->validRows, 'baris lengkap tetap harus lolos');
        $this->assertCount(1, $result->errors, 'baris pendek dilaporkan, bukan bikin fatal');
    }

    /** Baris kosong di akhir file (umum dari Excel) diabaikan. */
    public function test_baris_kosong_diabaikan(): void
    {
        Member::factory()->create(['member_number' => 'AGT-0001']);
        SavingsProduct::factory()->create(['code' => 'SIM-01']);

        $csv = self::HEADER."\n".'AGT-0001,SIM-01,,1000,2026-01-01,'."\n".",,,,,\n\n";

        $result = $this->service()->validateSavings($this->batch(), $this->csvPath($csv));

        $this->assertSame(1, $result->totalRows(), 'baris kosong tidak boleh dihitung sebagai baris data');
        $this->assertFalse($result->hasErrors());
    }

    /** File asli (koma, tanpa BOM) harus tetap bekerja seperti semula. */
    public function test_csv_koma_biasa_tetap_bekerja(): void
    {
        Member::factory()->create(['member_number' => 'AGT-0001']);
        SavingsProduct::factory()->create(['code' => 'SIM-01']);

        $csv = self::HEADER."\n".'AGT-0001,SIM-01,117-0101-00001,750000.50,2026-01-01,Migrasi SMIK'."\n";

        $result = $this->service()->validateSavings($this->batch(), $this->csvPath($csv));

        $this->assertFalse($result->hasErrors());
        $this->assertSame('117-0101-00001', $result->validRows[0]['data']['account_number']);
        $this->assertEquals(750000.50, $result->validRows[0]['data']['balance']);
    }
}
