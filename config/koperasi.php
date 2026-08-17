<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kode akun yang dirujuk langsung oleh kode program
    |--------------------------------------------------------------------------
    |
    | Sejumlah laporan tidak bisa menemukan akunnya lewat foreign key dan harus
    | menyebut kode akun secara langsung. Selama bagan akun masih bawaan
    | aplikasi, menuliskannya sebagai konstanta tidak menimbulkan masalah —
    | tapi koperasi yang membawa bagan akunnya sendiri dari sistem lama akan
    | memakai penomoran yang sama sekali berbeda, dan konstanta itu menunjuk
    | akun yang tidak ada lagi.
    |
    | Akibatnya diam: laporan tetap terbuka, hanya angkanya salah. Karena itu
    | kode-kode ini dipindah ke konfigurasi dan bisa ditimpa lewat .env.
    |
    */

    /*
     | Akun ekuitas tempat SHU tahun berjalan ditampilkan di Neraca.
     |
     | Belum ada mekanisme tutup buku yang menjurnal Pendapatan/Beban ke akun
     | ini, jadi saldonya dihitung live dari laba/rugi kumulatif. Kalau akunnya
     | tidak ada di Bagan Akun, baris itu hilang sama sekali dari Neraca — dan
     | Neraca langsung timpang sebesar laba/rugi berjalan, karena sisi Aset
     | memuat hasil usaha sementara sisi Ekuitas tidak.
     */
    'akun_shu_berjalan' => env('KOPERASI_AKUN_SHU_BERJALAN', '3150'),

    /*
     | Awalan kode akun Kas, Bank, dan setara kas — dipakai Arus Kas dan
     | dashboard Kas & Bank.
     |
     | Dicocokkan sebagai AWALAN, bukan kode persis, supaya seluruh akun
     | transaksi di bawah satu kelompok ikut terhitung tanpa perlu didaftarkan
     | satu per satu. Pada penomoran bawaan, "1101" mencakup 1101 sendiri;
     | pada bagan hasil migrasi, "1101" mencakup 1101100 sampai 1101600.
     */
    'akun_kas_bank' => array_filter(array_map(
        'trim',
        explode(',', (string) env('KOPERASI_AKUN_KAS_BANK', '1101,1102,1110'))
    )),

    /*
    |--------------------------------------------------------------------------
    | Batas cetakan PDF
    |--------------------------------------------------------------------------
    |
    | DomPDF menyimpan pohon style tiap sel di memori. Angka ini diukur di
    | produksi: 0,458 MB dan 0,02 detik per baris tabel laporan.
    |
    */

    'cetak_daftar_anggota_maks_baris' => (int) env('KOPERASI_CETAK_ANGGOTA_MAKS_BARIS', 1400),

    'cetak_laporan_maks_baris' => (int) env('KOPERASI_CETAK_LAPORAN_MAKS_BARIS', 1400),

    'cetak_laporan_kolom_landscape' => (int) env('KOPERASI_CETAK_LAPORAN_KOLOM_LANDSCAPE', 7),

    /*
     | Batas baris yang dirender ke layar hub Laporan.
     |
     | Bukan batas data: Export PDF/Excel tetap memakai seluruh baris yang lolos
     | saringan. Ini semata batas render — satu tabel HTML berisi puluhan ribu
     | baris menembus memory_limit saat Blade menyusun keluarannya, dan
     | saringan datatable yang menyisir seluruh baris tiap ketikan jadi macet
     | jauh sebelum itu.
     */
    'laporan_maks_baris_layar' => (int) env('KOPERASI_LAPORAN_MAKS_BARIS_LAYAR', 5000),

];
