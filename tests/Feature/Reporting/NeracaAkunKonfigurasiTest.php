<?php

namespace Tests\Feature\Reporting;

use App\Models\ChartOfAccount;
use App\Services\Reporting\FinancialReportEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Neraca memuat baris SHU tahun berjalan yang dihitung live dari laba/rugi,
 * karena belum ada mekanisme tutup buku yang menjurnalnya ke ekuitas.
 *
 * Akun tempat baris itu ditampilkan dulu ditulis mati sebagai '3150'. Koperasi
 * yang mengganti bagan akunnya dengan bagan sistem lama tidak punya kode itu,
 * dan barisnya lenyap tanpa peringatan — Neraca lalu timpang persis sebesar
 * laba/rugi berjalan. Kode akunnya kini dibaca dari konfigurasi.
 */
class NeracaAkunKonfigurasiTest extends TestCase
{
    use RefreshDatabase;

    private function akun(string $code, string $type, string $normal, string $statement, bool $postable = true): ChartOfAccount
    {
        return ChartOfAccount::query()->create([
            'code' => $code, 'name' => 'Akun '.$code, 'type' => $type,
            'normal_balance' => $normal, 'is_postable' => $postable, 'statement' => $statement,
        ]);
    }

    /** Akun SHU berjalan diambil dari konfigurasi, bukan konstanta '3150'. */
    public function test_baris_shu_berjalan_mengikuti_akun_di_konfigurasi(): void
    {
        config()->set('koperasi.akun_shu_berjalan', '3104999');

        $this->akun('3104999', 'EKUITAS', 'KREDIT', 'NERACA');
        $this->akun('1101100', 'ASET', 'DEBIT', 'NERACA');

        $neraca = app(FinancialReportEngine::class)->neraca(null, now()->toDateString());

        $kode = $neraca['rows']->map(fn ($row) => $row['account']->code);
        $this->assertContains('3104999', $kode->all());
    }

    /**
     * Tanpa akun SHU berjalan di Bagan Akun, barisnya hilang dan Neraca
     * timpang — inilah kegagalan yang membawa perbaikan ini.
     */
    public function test_tanpa_akun_shu_berjalan_barisnya_memang_tidak_ada(): void
    {
        config()->set('koperasi.akun_shu_berjalan', '3150');

        $this->akun('1101100', 'ASET', 'DEBIT', 'NERACA');

        $neraca = app(FinancialReportEngine::class)->neraca(null, now()->toDateString());

        $this->assertNotContains('3150', $neraca['rows']->map(fn ($row) => $row['account']->code)->all());
    }

    /** Akun kas dicocokkan sebagai awalan, sehingga akun turunan ikut terhitung. */
    public function test_akun_kas_dicocokkan_sebagai_awalan(): void
    {
        config()->set('koperasi.akun_kas_bank', ['1101', '1102']);

        $this->akun('1101100', 'ASET', 'DEBIT', 'NERACA');
        $this->akun('1101200', 'ASET', 'DEBIT', 'NERACA');
        $this->akun('1102101', 'ASET', 'DEBIT', 'NERACA');
        $this->akun('1104201', 'ASET', 'DEBIT', 'NERACA');

        $engine = app(FinancialReportEngine::class);
        $metode = new \ReflectionMethod($engine, 'cashBankAccounts');

        $kode = $metode->invoke($engine)->pluck('code')->sort()->values()->all();

        $this->assertSame(['1101100', '1101200', '1102101'], $kode);
    }

    /** Akun induk (non-postable) tidak ikut, supaya saldonya tidak dihitung dua kali. */
    public function test_akun_induk_tidak_ikut_terhitung(): void
    {
        config()->set('koperasi.akun_kas_bank', ['1101']);

        $this->akun('11010', 'ASET', 'DEBIT', 'NERACA', postable: false);
        $this->akun('1101100', 'ASET', 'DEBIT', 'NERACA');

        $engine = app(FinancialReportEngine::class);
        $metode = new \ReflectionMethod($engine, 'cashBankAccounts');

        $this->assertSame(['1101100'], $metode->invoke($engine)->pluck('code')->all());
    }
}
