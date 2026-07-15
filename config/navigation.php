<?php

/**
 * Sidebar Nav (03_DESIGN.md "Sidebar Nav") — grouped, permission-filtered
 * navigation shown on every authenticated page (layouts/app.blade.php).
 *
 * Each item's `permission` is checked with `@can` before rendering — a
 * null permission means "visible to every authenticated user" (e.g. the
 * main dashboard). Group headers with no visible items underneath are
 * hidden automatically by the sidebar partial.
 */
return [
    'groups' => [
        [
            'label' => 'Utama',
            'items' => [
                ['label' => 'Dashboard Utama', 'route' => 'admin.dashboard.index', 'permission' => null],
                ['label' => 'Kas & Bank', 'route' => 'admin.dashboard.kas-bank', 'permission' => 'kas_teller.read'],
                ['label' => 'RAT', 'route' => 'admin.dashboard.rat', 'permission' => 'rat.read'],
                ['label' => 'Kalender', 'route' => 'admin.kalender.kegiatan.index', 'permission' => 'kalender.read'],
            ],
        ],
        [
            'label' => 'Simpan Pinjam',
            'items' => [
                ['label' => 'Transaksi Teller', 'route' => 'staf.teller.create', 'permission' => 'simpanan.create'],
                ['label' => 'Pengajuan Pinjaman', 'route' => 'staf.pengajuan-pinjaman.create', 'permission' => 'pinjaman.create'],
                ['label' => 'Persetujuan Pinjaman', 'route' => 'admin.pinjaman.index', 'permission' => 'pinjaman.approve'],
                ['label' => 'Produk Simpanan', 'route' => 'admin.master.savings-products.index', 'permission' => 'master_data.read'],
                ['label' => 'Produk Pinjaman', 'route' => 'admin.master.loan-products.index', 'permission' => 'master_data.read'],
            ],
        ],
        [
            'label' => 'Akuntansi',
            'items' => [
                ['label' => 'Jurnal & Buku Besar', 'route' => 'admin.jurnal-buku-besar.index', 'permission' => 'jurnal.read'],
                ['label' => 'Jurnal Penyesuaian', 'route' => 'admin.jurnal-penyesuaian.create', 'permission' => 'jurnal.adjust'],
                ['label' => 'Laporan Keuangan', 'route' => 'admin.laporan-keuangan.index', 'permission' => 'laporan_keuangan.read'],
                ['label' => 'Report Builder', 'route' => 'admin.laporan-kustom.index', 'permission' => 'laporan_kustom.read'],
                ['label' => 'SHU', 'route' => 'admin.shu.index', 'permission' => 'shu.read'],
                ['label' => 'Paket RAT', 'route' => 'admin.rat.paket.download', 'permission' => 'rat.compose'],
                ['label' => 'Saldo Awal', 'route' => 'admin.saldo-awal.index', 'permission' => 'saldo_awal.read'],
                ['label' => 'Tarif & Parameter', 'route' => 'admin.tarif-parameter.index', 'permission' => 'tarif.read'],
            ],
        ],
        [
            'label' => 'Persediaan & Aset',
            'items' => [
                ['label' => 'Master Barang', 'route' => 'admin.master.products.index', 'permission' => 'master_data.read'],
                ['label' => 'Master Supplier', 'route' => 'admin.master.suppliers.index', 'permission' => 'master_data.read'],
                ['label' => 'Pembelian', 'route' => 'admin.pembelian.index', 'permission' => 'pembelian.read'],
                ['label' => 'Retur Pembelian', 'route' => 'admin.pembelian.retur.create', 'permission' => 'pembelian.create'],
                ['label' => 'Kasir (POS)', 'route' => 'staf.pos.create', 'permission' => 'pos.create'],
                ['label' => 'Retur Penjualan', 'route' => 'staf.pos.retur.create', 'permission' => 'pos.create'],
                ['label' => 'Koreksi Persediaan', 'route' => 'admin.persediaan.koreksi.index', 'permission' => 'persediaan_koreksi.read'],
                ['label' => 'Laporan Persediaan', 'route' => 'admin.persediaan.laporan.saldo', 'permission' => 'laporan_kustom.read'],
                ['label' => 'Kategori Aktiva Tetap', 'route' => 'admin.master.fixed-asset-categories.index', 'permission' => 'master_data.read'],
                ['label' => 'Aktiva Tetap', 'route' => 'admin.aktiva-tetap.index', 'permission' => 'aktiva_tetap.read'],
                ['label' => 'Pelepasan Aktiva Tetap', 'route' => 'admin.aktiva-tetap.pelepasan.create', 'permission' => 'aktiva_tetap.update'],
                ['label' => 'Laporan Aktiva Tetap', 'route' => 'admin.aktiva-tetap.laporan.daftar', 'permission' => 'laporan_kustom.read'],
            ],
        ],
        [
            'label' => 'Fasilitas & Kas',
            'items' => [
                ['label' => 'Kas Teller', 'route' => 'staf.kas.create', 'permission' => 'kas_teller.create'],
                ['label' => 'Iuran Kios (UPF)', 'route' => 'staf.upf.index', 'permission' => 'upf_tagihan.read'],
            ],
        ],
        [
            'label' => 'Usaha & Anggota',
            'items' => [
                ['label' => 'Unit Usaha', 'route' => 'admin.unit-usaha.index', 'permission' => 'unit_usaha.read'],
            ],
        ],
        [
            'label' => 'Sistem',
            'items' => [
                ['label' => 'Template Notifikasi', 'route' => 'admin.notifikasi.template.index', 'permission' => 'notifikasi_template.manage'],
                ['label' => 'Log Notifikasi', 'route' => 'admin.notifikasi.log.index', 'permission' => 'notifikasi_log.read'],
                ['label' => 'Keamanan & Audit', 'route' => 'admin.keamanan-audit.index', 'permission' => 'keamanan_audit.read'],
                ['label' => 'Pengaturan Branding', 'route' => 'admin.branding.edit', 'permission' => 'branding.manage'],
            ],
        ],
    ],
];
