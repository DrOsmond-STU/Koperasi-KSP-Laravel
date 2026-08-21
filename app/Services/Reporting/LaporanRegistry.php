<?php

namespace App\Services\Reporting;

/**
 * Whitelist of every "Laporan" hub module — one page per form/fitur that has
 * data (PRD ad-hoc request: 1 menu laporan menampung semua form/fitur).
 * Mirrors ReportTypeRegistry's shape (definitions/exists/columnsFor/labelFor)
 * but adds `group` (for the hub's category grouping), `filterable` (which
 * columns get a dropdown filter on the generic datatable view — low-
 * cardinality fields only; free-text columns rely on the search box), and
 * `date_column` (which column, if any, gets the "Periode ... s/d ..." date-
 * range filter — null for modules with no date-bearing column, e.g. pure
 * master data or a point-in-time snapshot like saldo_persediaan).
 *
 * LaporanController::fetch() is the only place that reads actual rows; this
 * class stays a pure whitelist of column keys/labels, same division of
 * responsibility as ReportTypeRegistry/ReportBuilderService.
 */
class LaporanRegistry
{
    /**
     * @return array<string, array{group: string, label: string, columns: array<string, string>, filterable: array<int, string>, date_column: ?string}>
     */
    public static function definitions(): array
    {
        return [
            'anggota' => [
                'group' => 'Keanggotaan',
                'label' => 'Data Anggota',
                'columns' => [
                    'member_number' => 'No. Anggota',
                    'name' => 'Nama',
                    'jenis' => 'Jenis Anggota',
                    'cabang' => 'Cabang',
                    'status' => 'Status',
                    'joined_at' => 'Tgl Bergabung',
                ],
                'filterable' => ['jenis', 'cabang', 'status'],
                'date_column' => 'joined_at',
            ],
            'jenis_anggota' => [
                'group' => 'Keanggotaan',
                'label' => 'Jenis Anggota',
                'columns' => [
                    'code' => 'Kode',
                    'name' => 'Nama',
                    'hak_suara' => 'Hak Suara',
                    'wajib_simpanan' => 'Wajib Simpanan Wajib',
                    'nominal_wajib' => 'Nominal Default',
                    'status' => 'Status',
                ],
                'filterable' => ['status'],
                'date_column' => null,
            ],
            'bagan_akun' => [
                'group' => 'Akuntansi',
                'label' => 'Bagan Akun (COA)',
                'columns' => [
                    'code' => 'Kode',
                    'name' => 'Nama',
                    'type' => 'Tipe',
                    'group' => 'Kelompok',
                    'normal_balance' => 'Saldo Normal',
                    'is_postable' => 'Postable',
                ],
                'filterable' => ['type', 'group', 'is_postable'],
                'date_column' => null,
            ],
            'simpanan_rekening' => [
                'group' => 'Simpanan & Pinjaman',
                'label' => 'Rekening Simpanan',
                'columns' => [
                    'account_number' => 'No. Rekening',
                    'member_name' => 'Nama Anggota',
                    'produk' => 'Produk',
                    'cabang' => 'Cabang',
                    'balance' => 'Saldo',
                    'status' => 'Status',
                    'opened_at' => 'Tgl Buka',
                ],
                'filterable' => ['produk', 'cabang', 'status'],
                'date_column' => 'opened_at',
            ],
            'simpanan_transaksi' => [
                'group' => 'Simpanan & Pinjaman',
                'label' => 'Transaksi Simpanan',
                'columns' => [
                    'tanggal' => 'Tanggal',
                    'account_number' => 'No. Rekening',
                    'member_name' => 'Nama Anggota',
                    'jenis' => 'Jenis',
                    'amount' => 'Jumlah',
                    'balance_after' => 'Saldo Akhir',
                    'cabang' => 'Cabang',
                    'status' => 'Status',
                ],
                'filterable' => ['jenis', 'cabang', 'status'],
                'date_column' => 'tanggal',
            ],
            'pinjaman' => [
                'group' => 'Simpanan & Pinjaman',
                'label' => 'Data Pinjaman',
                'columns' => [
                    'loan_number' => 'No. Pinjaman',
                    'member_name' => 'Nama Anggota',
                    'produk' => 'Produk',
                    'cabang' => 'Cabang',
                    'principal_amount' => 'Pokok Pinjaman',
                    'tenor_months' => 'Tenor (bln)',
                    'status' => 'Status',
                    'collectibility' => 'Kolektibilitas',
                    'disbursed_at' => 'Tgl Cair',
                ],
                'filterable' => ['produk', 'cabang', 'status', 'collectibility'],
                'date_column' => 'disbursed_at',
            ],
            'angsuran' => [
                'group' => 'Simpanan & Pinjaman',
                'label' => 'Jadwal Angsuran',
                'columns' => [
                    'loan_number' => 'No. Pinjaman',
                    'member_name' => 'Nama Anggota',
                    'installment_number' => 'Angsuran Ke',
                    'due_date' => 'Jatuh Tempo',
                    'total_amount' => 'Jumlah',
                    'paid_amount' => 'Terbayar',
                    'status' => 'Status',
                ],
                'filterable' => ['status'],
                'date_column' => 'due_date',
            ],
            'transaksi_pinjaman' => [
                'group' => 'Simpanan & Pinjaman',
                'label' => 'Transaksi Pinjaman (Pencairan)',
                'columns' => [
                    'loan_number' => 'No. Pinjaman',
                    'member_name' => 'Nama Anggota',
                    'produk' => 'Produk',
                    'cabang' => 'Cabang',
                    'tanggal_cair' => 'Tanggal Cair',
                    'jumlah' => 'Jumlah Dicairkan',
                ],
                'filterable' => ['produk', 'cabang'],
                'date_column' => 'tanggal_cair',
            ],
            'pembayaran_angsuran' => [
                'group' => 'Simpanan & Pinjaman',
                'label' => 'Transaksi Pembayaran Angsuran',
                'columns' => [
                    'tanggal' => 'Tanggal',
                    'loan_number' => 'No. Pinjaman',
                    'member_name' => 'Nama Anggota',
                    'cabang' => 'Cabang',
                    'jumlah' => 'Jumlah Bayar',
                    'pokok' => 'Porsi Pokok',
                    'jasa' => 'Porsi Jasa',
                    'saldo_akhir' => 'Sisa Pinjaman',
                    'status' => 'Status',
                ],
                'filterable' => ['cabang', 'status'],
                'date_column' => 'tanggal',
            ],
            'pengajuan_persetujuan_pinjaman' => [
                'group' => 'Simpanan & Pinjaman',
                'label' => 'Pengajuan & Persetujuan Pinjaman',
                'columns' => [
                    'tanggal' => 'Tanggal Keputusan',
                    'loan_number' => 'No. Pinjaman',
                    'member_name' => 'Nama Anggota',
                    'penyetuju' => 'Penyetuju',
                    'keputusan' => 'Keputusan',
                    'catatan' => 'Catatan',
                ],
                'filterable' => ['keputusan'],
                'date_column' => 'tanggal',
            ],
            'barang' => [
                'group' => 'Toko & Persediaan',
                'label' => 'Master Barang',
                'columns' => [
                    'code' => 'Kode',
                    'name' => 'Nama',
                    'category' => 'Kategori',
                    'unit' => 'Satuan',
                    'purchase_price' => 'Harga Beli',
                    'selling_price' => 'Harga Jual',
                    'status' => 'Status',
                ],
                'filterable' => ['category', 'status'],
                'date_column' => null,
            ],
            'supplier' => [
                'group' => 'Toko & Persediaan',
                'label' => 'Master Supplier',
                'columns' => [
                    'code' => 'Kode',
                    'name' => 'Nama',
                    'type' => 'Tipe',
                    'payment_term' => 'Termin Bayar',
                    'status' => 'Status',
                ],
                'filterable' => ['type', 'payment_term', 'status'],
                'date_column' => null,
            ],
            'pembelian' => [
                'group' => 'Toko & Persediaan',
                'label' => 'Transaksi Pembelian',
                'columns' => [
                    'purchase_number' => 'No. Pembelian',
                    'tanggal' => 'Tanggal',
                    'supplier_name' => 'Supplier',
                    'cabang' => 'Cabang',
                    'total_amount' => 'Total',
                    'payment_method' => 'Metode Bayar',
                    'status' => 'Status',
                    'payment_status' => 'Status Bayar',
                ],
                'filterable' => ['cabang', 'payment_method', 'status', 'payment_status'],
                'date_column' => 'tanggal',
            ],
            'retur_pembelian' => [
                'group' => 'Toko & Persediaan',
                'label' => 'Retur Pembelian',
                'columns' => [
                    'tanggal' => 'Tanggal',
                    'no_pembelian' => 'No. Pembelian',
                    'barang' => 'Barang',
                    'supplier_name' => 'Supplier',
                    'qty' => 'Qty',
                    'amount' => 'Jumlah',
                    'cabang' => 'Cabang',
                ],
                'filterable' => ['cabang'],
                'date_column' => 'tanggal',
            ],
            'koreksi_persediaan' => [
                'group' => 'Toko & Persediaan',
                'label' => 'Koreksi Persediaan',
                'columns' => [
                    'tanggal' => 'Tanggal',
                    'barang' => 'Barang',
                    'cabang' => 'Cabang',
                    'system_qty' => 'Qty Sistem',
                    'physical_qty' => 'Qty Fisik',
                    'variance_qty' => 'Selisih',
                    'amount' => 'Jumlah',
                    'status' => 'Status',
                ],
                'filterable' => ['cabang', 'status'],
                'date_column' => 'tanggal',
            ],
            'persediaan_kartu' => [
                'group' => 'Toko & Persediaan',
                'label' => 'Kartu Persediaan (Rincian)',
                'columns' => [
                    'tanggal' => 'Tanggal',
                    'barang' => 'Barang',
                    'cabang' => 'Cabang',
                    'jenis' => 'Keterangan',
                    'debet' => 'Debet (Masuk)',
                    'kredit' => 'Kredit (Keluar)',
                    'harga' => 'Harga',
                    'saldo' => 'Saldo',
                ],
                'filterable' => ['barang', 'cabang', 'jenis'],
                'date_column' => 'tanggal',
            ],
            'saldo_persediaan' => [
                'group' => 'Toko & Persediaan',
                'label' => 'Saldo Persediaan',
                'columns' => [
                    'kode' => 'Kode',
                    'barang' => 'Barang',
                    'qty' => 'Qty Saldo',
                    'nilai' => 'Nilai Persediaan',
                ],
                'filterable' => [],
                'date_column' => null,
            ],
            'kategori_aktiva_tetap' => [
                'group' => 'Aktiva Tetap',
                'label' => 'Kategori Aktiva Tetap',
                'columns' => [
                    'code' => 'Kode',
                    'name' => 'Nama',
                    'default_depreciation_method' => 'Metode Default',
                    'default_useful_life_months' => 'Umur Ekonomis (bln)',
                    'status' => 'Status',
                ],
                'filterable' => ['default_depreciation_method', 'status'],
                'date_column' => null,
            ],
            'aktiva_tetap' => [
                'group' => 'Aktiva Tetap',
                'label' => 'Data & Laporan Aktiva Tetap',
                'columns' => [
                    'code' => 'Kode',
                    'name' => 'Nama',
                    'kategori' => 'Kategori',
                    'cabang' => 'Cabang',
                    'acquisition_date' => 'Tgl Perolehan',
                    'acquisition_cost' => 'Harga Perolehan',
                    'akumulasi_penyusutan' => 'Akumulasi Penyusutan',
                    'book_value' => 'Nilai Buku',
                    'status' => 'Status',
                ],
                'filterable' => ['kategori', 'cabang', 'status'],
                'date_column' => 'acquisition_date',
            ],
            'pelepasan_aktiva_tetap' => [
                'group' => 'Aktiva Tetap',
                'label' => 'Pelepasan Aktiva Tetap',
                'columns' => [
                    'tanggal' => 'Tanggal Pelepasan',
                    'kode' => 'Kode Aktiva',
                    'nama' => 'Nama Aktiva',
                    'jenis_pelepasan' => 'Jenis Pelepasan',
                    'nilai_jual' => 'Nilai Jual',
                    'nilai_buku' => 'Nilai Buku',
                    'untung_rugi' => 'Untung/Rugi',
                    'status' => 'Status',
                ],
                'filterable' => ['jenis_pelepasan', 'status'],
                'date_column' => 'tanggal',
            ],
            'jenis_retribusi' => [
                'group' => 'Unit Usaha & Retribusi',
                'label' => 'Jenis Retribusi',
                'columns' => [
                    'code' => 'Kode',
                    'name' => 'Nama',
                    'percentage' => 'Persentase',
                    'status' => 'Status',
                ],
                'filterable' => ['status'],
                'date_column' => null,
            ],
            'retribusi_upf' => [
                'group' => 'Unit Usaha & Retribusi',
                'label' => 'Transaksi Retribusi UPF',
                'columns' => [
                    'tanggal' => 'Tanggal',
                    'transaction_number' => 'No. Transaksi',
                    'payer_name' => 'Pembayar',
                    'payer_type' => 'Tipe Pembayar',
                    'cabang' => 'Cabang',
                    'total_amount' => 'Jumlah',
                    'payment_method' => 'Metode Bayar',
                    'status' => 'Status',
                ],
                'filterable' => ['payer_type', 'cabang', 'payment_method', 'status'],
                'date_column' => 'tanggal',
            ],
            'unit_usaha' => [
                'group' => 'Unit Usaha & Retribusi',
                'label' => 'Master Unit Usaha',
                'columns' => [
                    'code' => 'Kode',
                    'name' => 'Nama',
                    'status' => 'Status',
                ],
                'filterable' => ['status'],
                'date_column' => null,
            ],
            'transaksi_unit_usaha' => [
                'group' => 'Unit Usaha & Retribusi',
                'label' => 'Transaksi Unit Usaha',
                'columns' => [
                    'tanggal' => 'Tanggal',
                    'unit_usaha' => 'Unit Usaha',
                    'cabang' => 'Cabang',
                    'jenis' => 'Jenis',
                    'amount' => 'Jumlah',
                    'description' => 'Keterangan',
                ],
                'filterable' => ['unit_usaha', 'cabang', 'jenis'],
                'date_column' => 'tanggal',
            ],
            'transaksi_pos' => [
                'group' => 'Kas & Kasir',
                'label' => 'Transaksi POS',
                'columns' => [
                    'tanggal' => 'Tanggal',
                    'sale_number' => 'No. Transaksi',
                    'cabang' => 'Cabang',
                    'metode_bayar' => 'Metode Bayar',
                    'total' => 'Total',
                ],
                'filterable' => ['cabang', 'metode_bayar'],
                'date_column' => 'tanggal',
            ],
            'transaksi_teller' => [
                'group' => 'Kas & Kasir',
                'label' => 'Transaksi Teller',
                'columns' => [
                    'tanggal' => 'Tanggal',
                    'kategori' => 'Kategori Kas',
                    'akun_kas' => 'Akun Kas',
                    'cabang' => 'Cabang',
                    'jumlah' => 'Jumlah',
                    'keterangan' => 'Keterangan',
                ],
                'filterable' => ['kategori', 'cabang'],
                'date_column' => 'tanggal',
            ],
            'kalender_kegiatan' => [
                'group' => 'Lainnya',
                'label' => 'Kalender Kegiatan',
                'columns' => [
                    'tanggal' => 'Tanggal',
                    'title' => 'Judul',
                    'location' => 'Lokasi',
                    'status' => 'Status',
                ],
                'filterable' => ['status'],
                'date_column' => 'tanggal',
            ],
            'jurnal_umum' => [
                'group' => 'Akuntansi',
                'label' => 'Jurnal Umum',
                'columns' => [
                    'tanggal' => 'Tanggal',
                    'description' => 'Keterangan',
                    'cabang' => 'Cabang',
                    'source_type' => 'Sumber',
                    'total_debit' => 'Total Debit',
                    'total_kredit' => 'Total Kredit',
                    'created_by_name' => 'Dibuat Oleh',
                ],
                'filterable' => ['cabang', 'source_type'],
                'date_column' => 'tanggal',
            ],
            'pengguna' => [
                'group' => 'Lainnya',
                'label' => 'Data Pengguna',
                'columns' => [
                    'name' => 'Nama',
                    'email' => 'Email',
                    'role' => 'Role',
                    'status' => 'Status',
                ],
                'filterable' => ['role', 'status'],
                'date_column' => null,
            ],

            // ---------------------------------------------------------------
            // Saldo Awal & Migrasi
            //
            // Enam entri di bawah membaca dari tabel-tabel migrasi (batch
            // saldo awal + snapshot pembayaran/jadwal historis), bukan dari
            // transaksi berjalan. Bentuknya sengaja dibuat mirip laporan hub
            // biasa supaya sub-total per anggota (dan sub total per kelompok
            // Neraca) muncul lewat mekanisme baris `_gaya` yang sama —
            // LaporanController::barisRingkasanCoa()/kelompokkanPerAnggota()
            // yang menyediakannya, dan generic print + show.blade.php yang
            // meregister style "baris-ringkasan"/"baris-judul".
            //
            // Dua entri Neraca berbagi kolom yang sama karena barisAkun()
            // mengeluarkan bentuk yang identik: yang membedakan hanya scope
            // (`neracaSaja: true` vs `false`) — Neraca menyusun aktiva →
            // pasiva → modal dengan sub total + SHU tahun berjalan; Neraca
            // Saldo memuat semua akun termasuk Laba/Rugi tanpa sub total
            // kelompok. Filter `tipe` & `laporan` bekerja di keduanya.
            // ---------------------------------------------------------------
            'saldo_awal_neraca' => [
                'group' => 'Saldo Awal & Migrasi',
                'label' => 'Saldo Awal — Neraca',
                'columns' => [
                    'kode' => 'Kode',
                    'nama_akun' => 'Nama Akun',
                    'tipe' => 'Tipe',
                    'laporan' => 'Laporan',
                    'debit' => 'Debit',
                    'kredit' => 'Kredit',
                ],
                'filterable' => ['tipe', 'laporan'],
                'date_column' => null,
            ],
            'saldo_awal_neraca_saldo' => [
                'group' => 'Saldo Awal & Migrasi',
                'label' => 'Saldo Awal — Neraca Saldo',
                'columns' => [
                    'kode' => 'Kode',
                    'nama_akun' => 'Nama Akun',
                    'tipe' => 'Tipe',
                    'laporan' => 'Laporan',
                    'debit' => 'Debit',
                    'kredit' => 'Kredit',
                ],
                'filterable' => ['tipe', 'laporan'],
                'date_column' => null,
            ],
            'saldo_awal_pinjaman' => [
                'group' => 'Saldo Awal & Migrasi',
                'label' => 'Saldo Awal Pinjaman',
                'columns' => [
                    'kode_anggota' => 'Kode Anggota',
                    'nama_anggota' => 'Nama Anggota',
                    'no_pinjaman_lama' => 'No. Pinjaman Lama',
                    'produk' => 'Produk',
                    'tanggal_akad' => 'Tgl Akad',
                    'plafon_awal' => 'Plafon Awal',
                    'sisa_pokok' => 'Sisa Pokok',
                    'sisa_jasa' => 'Sisa Jasa',
                    'tenor' => 'Tenor',
                    'sisa_tenor' => 'Sisa Tenor',
                    'jatuh_tempo' => 'Jatuh Tempo',
                    'kolektibilitas' => 'Kolektibilitas',
                ],
                'filterable' => ['produk', 'kolektibilitas'],
                'date_column' => 'tanggal_akad',
            ],
            'saldo_awal_simpanan' => [
                'group' => 'Saldo Awal & Migrasi',
                'label' => 'Saldo Awal Simpanan',
                'columns' => [
                    'kode_anggota' => 'Kode Anggota',
                    'nama_anggota' => 'Nama Anggota',
                    'produk' => 'Produk',
                    'no_rekening' => 'No. Rekening',
                    'saldo_awal' => 'Saldo Awal',
                    'keterangan' => 'Keterangan',
                ],
                'filterable' => ['produk'],
                'date_column' => null,
            ],
            'migrasi_historis_pembayaran' => [
                'group' => 'Saldo Awal & Migrasi',
                'label' => 'Migrasi Historis Pembayaran',
                'columns' => [
                    'tanggal' => 'Tanggal',
                    'no_pinjaman' => 'No. Pinjaman',
                    'nama_anggota' => 'Nama Anggota',
                    'pokok' => 'Pokok',
                    'jasa' => 'Jasa',
                    'jumlah' => 'Jumlah',
                    'sisa' => 'Sisa Setelah Bayar',
                    'keterangan' => 'Keterangan',
                ],
                'filterable' => [],
                'date_column' => 'tanggal',
            ],
            'migrasi_jadwal_pembayaran' => [
                'group' => 'Saldo Awal & Migrasi',
                'label' => 'Migrasi Jadwal Pembayaran',
                'columns' => [
                    'no_pinjaman' => 'No. Pinjaman',
                    'nama_anggota' => 'Nama Anggota',
                    'angsuran_ke' => 'Angsuran Ke',
                    'jatuh_tempo' => 'Jatuh Tempo',
                    'pokok' => 'Pokok',
                    'jasa' => 'Jasa',
                    'total' => 'Total',
                    'terbayar' => 'Terbayar',
                    'sisa' => 'Sisa',
                    'status' => 'Status',
                ],
                'filterable' => ['status'],
                'date_column' => 'jatuh_tempo',
            ],
        ];
    }

    public static function exists(string $module): bool
    {
        return isset(self::definitions()[$module]);
    }

    /**
     * @return array<string, string>
     */
    public static function columnsFor(string $module): array
    {
        return self::definitions()[$module]['columns'] ?? [];
    }

    /**
     * @return array<int, string>
     */
    public static function filterableFor(string $module): array
    {
        return self::definitions()[$module]['filterable'] ?? [];
    }

    public static function labelFor(string $module): ?string
    {
        return self::definitions()[$module]['label'] ?? null;
    }

    public static function dateColumnFor(string $module): ?string
    {
        return self::definitions()[$module]['date_column'] ?? null;
    }

    /**
     * @return array<string, array<string, array{label: string, columns: array<string, string>, filterable: array<int, string>}>>
     */
    public static function groups(): array
    {
        $grouped = [];

        foreach (self::definitions() as $module => $definition) {
            $grouped[$definition['group']][$module] = [
                'label' => $definition['label'],
                'columns' => $definition['columns'],
                'filterable' => $definition['filterable'],
            ];
        }

        return $grouped;
    }
}
