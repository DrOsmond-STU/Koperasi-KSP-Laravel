<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Native MySQL enum — extended via raw ALTER (pola sama dengan
     * 2026_07_19_090100_add_saldo_awal_to_stock_ledger_transaction_type_enum).
     * Menambah 'laporan_keuangan' supaya SignatureConfigController bisa
     * menyimpan slot tanda tangan cetakan Laporan Keuangan ("Disusun oleh
     * (Bendahara)" dst) — sebelum migration ini, kode sudah mengizinkan
     * grup ini (DEFAULT_SLOTS / StoreSignatureSlotRequest) tapi kolom DB
     * belum mendukungnya, sehingga insert gagal (SQLSTATE 1265 data
     * truncated) dan halaman /admin/pengaturan/tanda-tangan 500.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE document_signature_slots MODIFY document_group ENUM('pengajuan_pinjaman', 'pengajuan_penarikan', 'kas_keluar', 'kas_masuk', 'jurnal_umum', 'dokumen_gudang', 'aktiva_tetap', 'laporan_kas_bank', 'laporan_upf', 'laporan_keuangan', 'unit_usaha') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE document_signature_slots MODIFY document_group ENUM('pengajuan_pinjaman', 'pengajuan_penarikan', 'kas_keluar', 'kas_masuk', 'jurnal_umum', 'dokumen_gudang', 'aktiva_tetap', 'laporan_kas_bank', 'laporan_upf', 'unit_usaha') NOT NULL");
    }
};
