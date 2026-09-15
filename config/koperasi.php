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
     | Akun kas terakhir yang dipakai kalau sebuah transaksi tidak menemukan
     | akun kas cabangnya sendiri (branches.cash_account_id, diatur lewat
     | Pengaturan → Kas Cabang).
     |
     | Dulu kode '1101' ditulis langsung di sepuluh service sebagai konstanta
     | masing-masing. Koperasi yang membawa bagan akunnya sendiri memakai
     | penomoran lain dan justru menjadikan 1101 akun header — dan karena
     | akun header ditolak JournalEngine, setiap posting yang jatuh ke sini
     | gagal total. Sekarang kodenya satu tempat dan bisa ditimpa lewat .env.
     |
     | Kosongkan (KOPERASI_AKUN_KAS_BAWAAN=) untuk mematikan jaring pengaman
     | ini sama sekali: transaksi yang cabangnya belum diatur akan ditolak
     | dengan pesan yang menyebut cabangnya, alih-alih diam-diam memakai akun
     | kas yang belum tentu benar.
     */
    'akun_kas_bawaan' => env('KOPERASI_AKUN_KAS_BAWAAN', '1101'),

    /*
     | Dua alur memakai akun kas satu cabang tertentu, bukan akun kas cabang
     | yang tercatat di transaksinya sendiri:
     |
     | - Simpanan (setor/tarik/buka rekening). Seluruh rekening simpanan di
     |   produksi ber-branch_id ke cabang root "KPPD Pusat", sehingga resolusi
     |   per-cabang rekening selalu jatuh ke kas KPPD Pusat — bukan kas Unit
     |   KSP yang dimaksud staf (laporan 24 Agu 2026).
     | - Retribusi UPF, yang memang selalu diterima petugas UPF.
     |
     | Yang disebut di sini kode CABANG-nya, bukan kode akun: akun kasnya
     | sendiri tetap mengikuti Pengaturan → Kas Cabang, jadi bisa diganti
     | tanpa deploy.
     */
    'cabang_kas_simpanan' => env('KOPERASI_CABANG_KAS_SIMPANAN', '001'),

    'cabang_kas_retribusi' => env('KOPERASI_CABANG_KAS_RETRIBUSI', '003'),

    /*
    |--------------------------------------------------------------------------
    | Kewajiban MFA untuk peran internal
    |--------------------------------------------------------------------------
    |
    | SECURITY.md mewajibkan MFA bagi peran internal, dan
    | EnsureMfaIsEnabledForInternalRoles menegakkannya dengan menolak 403 bila
    | two_factor_confirmed_at kosong.
    |
    | Dimatikan sementara pada 20-08-2026. Sebabnya: halaman tantangan 2FA
    | (route two-factor.login) tidak pernah dibangun -- config/fortify.php
    | memakai 'views' => false sehingga Fortify hanya mendaftarkan endpoint
    | POST. Akibatnya siapa pun yang mengaktifkan MFA langsung terkunci: login
    | benar, lalu diarahkan ke halaman yang tidak ada, dan berakhir 500. Tiga
    | akun produksi terkunci begitu. Setelah penanda MFA dibersihkan, middleware
    | ini justru menolak semuanya dengan 403 -- dan endpoint untuk mengaktifkan
    | MFA sudah ikut hilang, jadi tidak ada jalan keluar dari dalam aplikasi.
    |
    | Jadi kewajibannya menuntut sesuatu yang sistemnya sendiri belum bisa
    | sediakan. Nyalakan lagi (KOPERASI_WAJIB_MFA=true) SETELAH halaman
    | tantangan 2FA tersedia dan pengguna bisa benar-benar mengaktifkannya.
    |
    */

    'wajib_mfa' => filter_var(env('KOPERASI_WAJIB_MFA', false), FILTER_VALIDATE_BOOLEAN),

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
