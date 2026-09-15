<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cabang pemilik seluruh transaksi pinjaman: pengajuan, pencairan, dan
 * angsuran.
 *
 * Selama ini cabang pinjaman diturunkan dari cabang si ANGGOTA
 * (`members.branch_id`, lihat LoanApplicationController::store()). Itu
 * menjawab pertanyaan yang salah. Cabang pada sebuah transaksi dipakai
 * untuk laba rugi per unit usaha — jadi yang harus tercatat adalah unit
 * yang MENJALANKAN pinjamannya, bukan unit tempat anggotanya terdaftar.
 * Anggota UPF yang meminjam tetap meminjam dari unit simpan pinjam;
 * pendapatan jasanya milik unit itu, bukan UPF.
 *
 * Akibatnya terlihat jelas di produksi 16 Sep 2026 — tidak ada satu pun
 * jurnal pinjaman di cabang USP:
 *
 *   pinjaman   : 131 di KPPD Pusat (impor saldo awal), 18 di KSP, 1 di USP
 *   angsuran   : 6.425 seluruhnya di KPPD Pusat
 *   jurnal     : 1 pencairan di KSP, 1.090 angsuran di KPPD Pusat, 0 di USP
 *
 * Nullable, dan selama kosong perilakunya persis seperti sebelumnya
 * (cabang anggota) — instalasi yang memang membukukan pinjaman per cabang
 * anggota tidak berubah sedikit pun.
 *
 * Disimpan sebagai id, bukan kode cabang seperti `cabang_kas_simpanan` di
 * config/koperasi.php. Kode cabang di config itu milik satu koperasi
 * tertentu yang ikut terbawa ke semua koperasi lain; setelan ini justru
 * tidak boleh begitu, dan dengan FK + nullOnDelete ia ikut bersih sendiri
 * kalau cabangnya dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_settings', function (Blueprint $table) {
            $table->foreignId('loan_branch_id')->nullable()->after('loan_disbursement_account_id')
                ->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_branch_id');
        });
    }
};
