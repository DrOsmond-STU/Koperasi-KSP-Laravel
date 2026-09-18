<?php

namespace App\Services\Reporting;

use Illuminate\Support\Collection;

/**
 * Menjumlahkan kolom nominal sebuah laporan.
 *
 * Baris laporan sudah berupa TEKS terformat waktu sampai ke sini ("Rp
 * 1.250.000"), bukan angka — LaporanController memformatnya lebih dulu supaya
 * layar, PDF, dan Excel menampilkan hal yang sama. Jadi penjumlahan di sini
 * membaca kembali angkanya dari teks itu, memakai aturan penulisan Indonesia
 * (titik ribuan, koma desimal).
 *
 * Kelas ini juga yang menentukan kolom mana BOLEH dijumlah, dan jawabannya
 * dipakai bersama oleh ketiga keluaran: PDF dan Excel memanggilnya langsung,
 * sedangkan layar menerima daftar kolomnya lewat atribut data lalu
 * menjumlahkan baris yang sedang terlihat. Dengan begitu ketiganya tidak
 * mungkin berbeda pendapat soal kolom mana yang punya total.
 *
 * Yang TIDAK dijumlah, dan alasannya:
 *
 *   - Nomor dokumen (no. rekening, no. pinjaman). Kebetulan berisi angka,
 *     tapi menjumlahkannya tidak berarti apa-apa.
 *   - Saldo berjalan (Sisa Pinjaman, Saldo Akhir, Saldo kartu persediaan).
 *     Tiap baris memuat sisa SETELAH transaksi itu; menjumlahkannya
 *     menghasilkan angka raksasa yang tidak mewakili apa pun.
 *   - Harga satuan, persentase, tenor, nomor urut angsuran. Sama sekali bukan
 *     besaran yang bertambah kalau barisnya bertambah.
 *
 * Yang dua terakhir tidak bisa ditebak dari bentuk teksnya, jadi disebut satu
 * per satu di LaporanRegistry lewat kunci `tidak_dijumlah`.
 */
class PenjumlahLaporan
{
    /**
     * Sel dianggap angka kalau isinya hanya digit, pemisah ribuan/desimal,
     * dan boleh berawalan "Rp". Tanda kurung dipakai untuk nilai negatif.
     * Pola ini kembar dengan yang dipakai prints/laporan/generic.blade.php
     * untuk menentukan perataan kolom.
     */
    private const POLA_ANGKA = '/^\(?-?\s*(Rp\s*)?-?[\d., ]+\)?$/i';

    /**
     * Nomor dokumen tidak pernah dijumlah di laporan mana pun, jadi dikenali
     * dari nama kolomnya saja alih-alih disebut satu per satu di registry.
     */
    private const POLA_NOMOR_DOKUMEN = '/^(no_|nomor|kode$|code$)|_(number|no)$/i';

    public static function apakahAngka(mixed $nilai): bool
    {
        return is_string($nilai)
            && preg_match(self::POLA_ANGKA, trim($nilai)) === 1
            && preg_match('/\d/', $nilai) === 1;
    }

    /**
     * Baca angka dari teks berformat Indonesia: "Rp 1.250.000,50" -> 1250000.5
     * dan "(Rp 2.000)" -> -2000.
     */
    public static function bacaAngka(string $teks): float
    {
        $teks = trim($teks);
        $negatif = str_starts_with($teks, '(') || str_contains($teks, '-');

        $bersih = preg_replace('/[^\d,]/', '', $teks) ?? '';
        $bersih = str_replace(',', '.', $bersih);

        $nilai = (float) $bersih;

        return $negatif ? -$nilai : $nilai;
    }

    /**
     * Baris ringkasan/judul yang sudah dibuat laporannya sendiri tidak boleh
     * ikut dijumlah — kalau ikut, sub totalnya terhitung dua kali.
     *
     * @param  array<string, mixed>  $row
     */
    public static function barisData(array $row): bool
    {
        return ($row['_gaya'] ?? null) === null;
    }

    /**
     * Kolom mana yang punya total.
     *
     * @param  array<string, string>  $columns
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $dikecualikan
     * @return array<int, string>
     */
    public static function kolomDijumlah(array $columns, Collection $rows, array $dikecualikan = []): array
    {
        $data = $rows->filter(fn ($row) => self::barisData($row));

        return array_values(array_filter(
            array_keys($columns),
            fn (string $key) => ! in_array($key, $dikecualikan, true)
                && preg_match(self::POLA_NOMOR_DOKUMEN, $key) !== 1
                && $data->contains(fn ($row) => self::apakahAngka($row[$key] ?? null)),
        ));
    }

    /**
     * Satu baris total, siap ditempel di kaki tabel.
     *
     * Kolom pertama yang tidak dijumlah dipakai untuk menaruh kata "TOTAL",
     * supaya barisnya punya label tanpa perlu kolom tambahan. Mengembalikan
     * null kalau tidak ada satu pun kolom yang bisa dijumlah — laporan daftar
     * (anggota, supplier) memang tidak perlu baris total.
     *
     * @param  array<string, string>  $columns
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $dikecualikan
     * @return array<string, string>|null
     */
    public static function baris(array $columns, Collection $rows, array $dikecualikan = []): ?array
    {
        $dijumlah = self::kolomDijumlah($columns, $rows, $dikecualikan);

        if ($dijumlah === []) {
            return null;
        }

        $data = $rows->filter(fn ($row) => self::barisData($row));

        $total = [];
        foreach (array_keys($columns) as $key) {
            $total[$key] = '';
        }

        foreach ($dijumlah as $key) {
            $jumlah = $data->sum(fn ($row) => self::apakahAngka($row[$key] ?? null)
                ? self::bacaAngka((string) $row[$key])
                : 0.0);

            // Awalan "Rp" diikutkan hanya kalau kolomnya memang memakainya,
            // supaya kolom kuantitas tidak mendadak tampil sebagai uang.
            $contoh = $data->first(fn ($row) => self::apakahAngka($row[$key] ?? null));
            $pakaiRp = $contoh !== null && stripos((string) $contoh[$key], 'rp') !== false;

            // Tanda minus di depan awalan ("-Rp 1.234"), bukan di antaranya
            // ("Rp -1.234") — bentuk kedua tidak lazim dibaca di Indonesia.
            $total[$key] = ($jumlah < 0 ? '-' : '')
                .($pakaiRp ? 'Rp ' : '')
                .number_format(abs($jumlah), 0, ',', '.');
        }

        foreach (array_keys($columns) as $key) {
            if (! in_array($key, $dijumlah, true)) {
                $total[$key] = 'TOTAL';
                break;
            }
        }

        return $total;
    }
}
