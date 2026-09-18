<?php

namespace Tests\Unit\Reporting;

use App\Services\Reporting\LaporanRegistry;
use App\Services\Reporting\PenjumlahLaporan;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Baris TOTAL di kaki laporan.
 *
 * Yang dijaga di sini bukan cuma "angkanya benar dijumlah", melainkan
 * kebalikannya: kolom yang TIDAK boleh dijumlah tidak boleh kebobolan. Total
 * yang salah di laporan keuangan lebih berbahaya daripada tidak ada total
 * sama sekali — angkanya terlihat resmi, dan tidak ada yang akan mengeceknya
 * ulang.
 */
class PenjumlahLaporanTest extends TestCase
{
    /** @param  array<int, array<string, string>>  $rows */
    private function baris(array $rows): Collection
    {
        return collect($rows);
    }

    public function test_kolom_rupiah_dijumlah_dan_tetap_berformat_rupiah(): void
    {
        $total = PenjumlahLaporan::baris(
            ['tanggal' => 'Tanggal', 'jumlah' => 'Jumlah Bayar'],
            $this->baris([
                ['tanggal' => '01-09-2026', 'jumlah' => 'Rp 1.250.000'],
                ['tanggal' => '02-09-2026', 'jumlah' => 'Rp 750.500'],
            ]),
        );

        $this->assertSame('Rp 2.000.500', $total['jumlah']);
    }

    public function test_kolom_bukan_angka_dipakai_untuk_label_total(): void
    {
        $total = PenjumlahLaporan::baris(
            ['tanggal' => 'Tanggal', 'jumlah' => 'Jumlah'],
            $this->baris([['tanggal' => '01-09-2026', 'jumlah' => 'Rp 10.000']]),
        );

        $this->assertSame('TOTAL', $total['tanggal']);
    }

    public function test_kolom_angka_tanpa_rupiah_tidak_mendadak_jadi_uang(): void
    {
        $total = PenjumlahLaporan::baris(
            ['barang' => 'Barang', 'qty' => 'Qty'],
            $this->baris([
                ['barang' => 'Beras', 'qty' => '12'],
                ['barang' => 'Gula', 'qty' => '8'],
            ]),
        );

        $this->assertSame('20', $total['qty']);
    }

    public function test_baris_ringkasan_dan_judul_tidak_ikut_dijumlah(): void
    {
        // Laporan yang membuat sub totalnya sendiri akan terhitung dua kali
        // kalau baris rekap itu ikut dijumlah.
        $total = PenjumlahLaporan::baris(
            ['nama' => 'Nama', 'jumlah' => 'Jumlah'],
            $this->baris([
                ['nama' => 'Cabang A', 'jumlah' => '', '_gaya' => 'judul'],
                ['nama' => 'Andi', 'jumlah' => 'Rp 100.000'],
                ['nama' => 'Budi', 'jumlah' => 'Rp 200.000'],
                ['nama' => 'Sub Total', 'jumlah' => 'Rp 300.000', '_gaya' => 'ringkasan'],
            ]),
        );

        $this->assertSame('Rp 300.000', $total['jumlah']);
    }

    public function test_nomor_dokumen_tidak_pernah_dijumlah(): void
    {
        // Nomor rekening yang kebetulan berupa angka akan lolos deteksi
        // "kolom nominal" kalau hanya bentuknya yang diperiksa.
        $kolom = PenjumlahLaporan::kolomDijumlah(
            ['no_rekening' => 'No. Rekening', 'kode' => 'Kode', 'saldo' => 'Saldo'],
            $this->baris([['no_rekening' => '1002003', 'kode' => '4102201', 'saldo' => 'Rp 50.000']]),
        );

        $this->assertSame(['saldo'], $kolom);
    }

    public function test_kolom_yang_dikecualikan_registry_tidak_dijumlah(): void
    {
        $kolom = PenjumlahLaporan::kolomDijumlah(
            ['jumlah' => 'Jumlah', 'saldo_akhir' => 'Sisa Pinjaman'],
            $this->baris([['jumlah' => 'Rp 100.000', 'saldo_akhir' => 'Rp 900.000']]),
            ['saldo_akhir'],
        );

        $this->assertSame(['jumlah'], $kolom);
    }

    public function test_laporan_tanpa_kolom_nominal_tidak_punya_baris_total(): void
    {
        $total = PenjumlahLaporan::baris(
            ['nama' => 'Nama', 'status' => 'Status'],
            $this->baris([['nama' => 'Andi', 'status' => 'Aktif']]),
        );

        $this->assertNull($total);
    }

    public function test_nilai_negatif_dalam_kurung_mengurangi(): void
    {
        $total = PenjumlahLaporan::baris(
            ['akun' => 'Akun', 'untung_rugi' => 'Untung/Rugi'],
            $this->baris([
                ['akun' => 'Meja', 'untung_rugi' => 'Rp 500.000'],
                ['akun' => 'Kursi', 'untung_rugi' => '(Rp 800.000)'],
            ]),
        );

        $this->assertSame('-Rp 300.000', $total['untung_rugi']);
    }

    public function test_sel_kosong_dan_strip_diabaikan_bukan_dianggap_nol_yang_merusak(): void
    {
        $total = PenjumlahLaporan::baris(
            ['nama' => 'Nama', 'jumlah' => 'Jumlah'],
            $this->baris([
                ['nama' => 'Andi', 'jumlah' => 'Rp 100.000'],
                ['nama' => 'Budi', 'jumlah' => '-'],
                ['nama' => 'Cici', 'jumlah' => ''],
            ]),
        );

        $this->assertSame('Rp 100.000', $total['jumlah']);
    }

    /**
     * Laporan yang disebut pengguna. Sisa Pinjaman adalah saldo BERJALAN —
     * tiap baris memuat sisa setelah angsuran itu, jadi menjumlahkannya
     * menghasilkan angka raksasa yang tidak mewakili apa pun.
     */
    public function test_pembayaran_angsuran_menjumlah_nominal_tapi_bukan_sisa_pinjaman(): void
    {
        $kolom = PenjumlahLaporan::kolomDijumlah(
            LaporanRegistry::columnsFor('pembayaran_angsuran'),
            $this->baris([[
                'tanggal' => '01-09-2026',
                'loan_number' => '51-100H-260813-9703',
                'member_name' => 'Andi',
                'cabang' => 'USP',
                'jumlah' => 'Rp 300.000',
                'pokok' => 'Rp 200.000',
                'jasa' => 'Rp 90.000',
                'denda' => 'Rp 10.000',
                'saldo_akhir' => 'Rp 2.700.000',
                'status' => 'Normal',
            ]]),
            LaporanRegistry::tidakDijumlahFor('pembayaran_angsuran'),
        );

        $this->assertSame(['jumlah', 'pokok', 'jasa', 'denda'], $kolom);
        $this->assertNotContains('saldo_akhir', $kolom);
    }

    /**
     * Pagar mutu untuk registry itu sendiri: menyebut kolom yang sudah tidak
     * ada lagi membuat pengecualiannya diam-diam tidak berlaku, dan kolomnya
     * ikut dijumlah tanpa ada yang sadar.
     */
    public function test_setiap_kolom_yang_dikecualikan_benar_benar_ada(): void
    {
        foreach (LaporanRegistry::definitions() as $modul => $definisi) {
            foreach ($definisi['tidak_dijumlah'] ?? [] as $kolom) {
                $this->assertArrayHasKey(
                    $kolom,
                    $definisi['columns'],
                    "Laporan '{$modul}' mengecualikan kolom '{$kolom}' yang tidak ada di daftar kolomnya.",
                );
            }
        }
    }
}
