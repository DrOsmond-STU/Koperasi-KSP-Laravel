<?php

/**
 * Sidebar Nav (03_DESIGN.md "Sidebar Nav") — grouped, permission-filtered
 * navigation shown on every authenticated page (layouts/app.blade.php).
 *
 * Each item's `permission` is checked with `@can` before rendering — a
 * null permission means "visible to every authenticated user" (e.g. the
 * main dashboard). Group headers with no visible items underneath are
 * hidden automatically by the sidebar partial.
 *
 * `icon` maps to a key in resources/views/partials/icon.blade.php's icon
 * set — falls back to a generic dot icon if omitted or unrecognized.
 */
return [
    'groups' => [
        [
            'label' => 'Utama',
            'items' => [
                ['label' => 'Dashboard Utama', 'route' => 'admin.dashboard.index', 'permission' => null, 'icon' => 'home'],
                ['label' => 'Kas & Bank', 'route' => 'admin.dashboard.kas-bank', 'permission' => 'kas_teller.read', 'icon' => 'wallet'],
                ['label' => 'RAT', 'route' => 'admin.dashboard.rat', 'permission' => 'rat.read', 'icon' => 'users'],
                ['label' => 'Kalender', 'route' => 'admin.dashboard.kalender', 'permission' => 'kalender.read', 'icon' => 'calendar'],
            ],
        ],
        [
            'label' => 'Simpan Pinjam',
            'items' => [
                ['label' => 'Transaksi Teller', 'route' => 'staf.teller.create', 'permission' => 'simpanan.create', 'icon' => 'repeat'],
                ['label' => 'Pengajuan Pinjaman', 'route' => 'staf.pengajuan-pinjaman.create', 'permission' => 'pinjaman.create', 'icon' => 'send'],
                ['label' => 'Catat Angsuran', 'route' => 'staf.angsuran.create', 'permission' => 'pinjaman.create', 'icon' => 'repeat'],
                ['label' => 'Persetujuan Pinjaman', 'route' => 'admin.pinjaman.index', 'permission' => 'pinjaman.approve', 'icon' => 'check-circle'],
                ['label' => 'Kategori Simpanan', 'route' => 'admin.master.savings-product-categories.index', 'permission' => 'master_data.read', 'icon' => 'tag'],
                ['label' => 'Produk Simpanan', 'route' => 'admin.master.savings-products.index', 'permission' => 'master_data.read', 'icon' => 'piggy-bank'],
                ['label' => 'Produk Pinjaman', 'route' => 'admin.master.loan-products.index', 'permission' => 'master_data.read', 'icon' => 'credit-card'],
            ],
        ],
        [
            'label' => 'Akuntansi',
            'items' => [
                ['label' => 'Bagan Akun', 'route' => 'admin.master.chart-of-accounts.index', 'permission' => 'chart_of_account.read', 'icon' => 'archive'],
                ['label' => 'Import Bagan Akun', 'route' => 'admin.master.chart-of-accounts.import.form', 'permission' => 'chart_of_account.create', 'icon' => 'database'],
                ['label' => 'Hapus Massal Akun', 'route' => 'admin.master.chart-of-accounts.purge.form', 'permission' => 'chart_of_account.delete', 'icon' => 'archive'],
                ['label' => 'Jurnal & Buku Besar', 'route' => 'admin.jurnal-buku-besar.index', 'permission' => 'jurnal.read', 'icon' => 'book'],
                ['label' => 'Jurnal Umum', 'route' => 'admin.jurnal-umum.create', 'permission' => 'jurnal.create', 'icon' => 'file-text'],
                ['label' => 'Jurnal Penyesuaian', 'route' => 'admin.jurnal-penyesuaian.create', 'permission' => 'jurnal.adjust', 'icon' => 'sliders'],
                ['label' => 'Saldo Awal', 'route' => 'admin.saldo-awal.index', 'permission' => 'saldo_awal.read', 'icon' => 'database'],
                ['label' => 'Import Jadwal Angsuran', 'route' => 'admin.pinjaman.jadwal.import.form', 'permission' => 'saldo_awal.create', 'icon' => 'repeat'],
                ['label' => 'Tarif & Parameter', 'route' => 'admin.tarif-parameter.index', 'permission' => 'tarif.read', 'icon' => 'sliders'],
            ],
        ],
        [
            'label' => 'Persediaan & Aset',
            'items' => [
                ['label' => 'Master Barang', 'route' => 'admin.master.products.index', 'permission' => 'master_data.read', 'icon' => 'package'],
                ['label' => 'Master Supplier', 'route' => 'admin.master.suppliers.index', 'permission' => 'master_data.read', 'icon' => 'truck'],
                ['label' => 'Pembelian', 'route' => 'admin.pembelian.index', 'permission' => 'pembelian.read', 'icon' => 'shopping-cart'],
                ['label' => 'Retur Pembelian', 'route' => 'admin.pembelian.retur.create', 'permission' => 'pembelian.create', 'icon' => 'rotate-ccw'],
                ['label' => 'Kasir (POS)', 'route' => 'staf.pos.create', 'permission' => 'pos.create', 'icon' => 'monitor'],
                ['label' => 'Retur Penjualan', 'route' => 'staf.pos.retur.create', 'permission' => 'pos.create', 'icon' => 'rotate-ccw'],
                ['label' => 'Koreksi Persediaan', 'route' => 'admin.persediaan.koreksi.index', 'permission' => 'persediaan_koreksi.read', 'icon' => 'clipboard'],
                ['label' => 'Kategori Aktiva Tetap', 'route' => 'admin.master.fixed-asset-categories.index', 'permission' => 'master_data.read', 'icon' => 'folder'],
                ['label' => 'Aktiva Tetap', 'route' => 'admin.aktiva-tetap.index', 'permission' => 'aktiva_tetap.read', 'icon' => 'archive'],
                ['label' => 'Pelepasan Aktiva Tetap', 'route' => 'admin.aktiva-tetap.pelepasan.create', 'permission' => 'aktiva_tetap.update', 'icon' => 'log-out'],
            ],
        ],
        [
            'label' => 'Fasilitas & Kas',
            'items' => [
                ['label' => 'Kas Teller', 'route' => 'staf.kas.create', 'permission' => 'kas_teller.create', 'icon' => 'wallet'],
                ['label' => 'Retribusi (UPF)', 'route' => 'staf.retribusi-upf.index', 'permission' => 'retribusi_upf.read', 'icon' => 'tag'],
                ['label' => 'Jenis Retribusi', 'route' => 'admin.master.retribution-types.index', 'permission' => 'master_data.read', 'icon' => 'tag'],
            ],
        ],
        [
            'label' => 'Usaha & Anggota',
            'items' => [
                ['label' => 'Unit Usaha', 'route' => 'admin.unit-usaha.index', 'permission' => 'unit_usaha.read', 'icon' => 'briefcase'],
                ['label' => 'Data Anggota', 'route' => 'admin.members.index', 'permission' => 'master_data.read', 'icon' => 'users'],
                ['label' => 'Jenis Anggota', 'route' => 'admin.master.member-types.index', 'permission' => 'master_data.read', 'icon' => 'tag'],
                ['label' => 'Template Kartu Anggota', 'route' => 'admin.master.member-card-templates.index', 'permission' => 'member_card.manage', 'icon' => 'credit-card'],
            ],
        ],
        [
            'label' => 'Laporan',
            'items' => [
                ['label' => 'Laporan Keuangan', 'route' => 'admin.laporan-keuangan.index', 'permission' => 'laporan_keuangan.read', 'icon' => 'bar-chart'],
                ['label' => 'Laporan', 'route' => 'admin.laporan.index', 'permission' => 'laporan.read', 'icon' => 'list'],
                ['label' => 'Report Builder', 'route' => 'admin.laporan-kustom.index', 'permission' => 'laporan_kustom.read', 'icon' => 'pie-chart'],
                ['label' => 'SHU', 'route' => 'admin.shu.index', 'permission' => 'shu.read', 'icon' => 'trending-up'],
                ['label' => 'Paket RAT', 'route' => 'admin.rat.paket.download', 'permission' => 'rat.compose', 'icon' => 'download'],
            ],
        ],
        [
            'label' => 'Sistem',
            'items' => [
                ['label' => 'Manajemen Pengguna', 'route' => 'admin.users.index', 'permission' => 'user.manage', 'icon' => 'user'],
                ['label' => 'Role & Permission', 'route' => 'admin.role-permission.index', 'permission' => 'role.manage', 'icon' => 'shield'],
                ['label' => 'Template Notifikasi', 'route' => 'admin.notifikasi.template.index', 'permission' => 'notifikasi_template.manage', 'icon' => 'mail'],
                ['label' => 'Log Notifikasi', 'route' => 'admin.notifikasi.log.index', 'permission' => 'notifikasi_log.read', 'icon' => 'bell'],
                ['label' => 'Keamanan & Audit', 'route' => 'admin.keamanan-audit.index', 'permission' => 'keamanan_audit.read', 'icon' => 'shield-check'],
                ['label' => 'Pengaturan Branding', 'route' => 'admin.branding.edit', 'permission' => 'branding.manage', 'icon' => 'image'],
                ['label' => 'Pengaturan Cetakan', 'route' => 'admin.pengaturan.cetakan.edit', 'permission' => 'cetakan.manage', 'icon' => 'file-text'],
                ['label' => 'Setup Tanda Tangan', 'route' => 'admin.pengaturan.tanda-tangan.index', 'permission' => 'cetakan.manage', 'icon' => 'sliders'],
            ],
        ],
    ],
];
