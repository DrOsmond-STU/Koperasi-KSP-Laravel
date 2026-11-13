<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tanggal pembayaran SEBENARNYA — sebelumnya tidak ada kolom ini sama
     * sekali, sehingga "Tanggal Bayar" yang tercetak di semua laporan/PDF
     * selalu diambil dari created_at (kapan baris ini DICATAT ke sistem,
     * bukan kapan pembayaran itu terjadi). Untuk pembayaran yang dicatat
     * mundur (staf menyusulkan angsuran lama), created_at ikut-ikutan
     * bertanggal "hari ini" untuk semua entri — itulah sebabnya banyak
     * baris riwayat pembayaran tampak bertanggal sama.
     *
     * Nullable & tidak di-backfill dengan sengaja: baris lama (termasuk
     * ~21 ribu baris hasil migrasi riwayat pembayaran — lihat komentar di
     * LaporanController::show()) tidak punya tanggal aslinya lagi untuk
     * diisi ulang secara akurat — memaksa created_at ke kolom ini hanya
     * akan menyalin tanggal yang sudah salah, bukan memperbaikinya. Baris
     * baru SELALU mengisi kolom ini lewat LoanRepaymentService::recordPayment().
     */
    public function up(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->date('transaction_date')->nullable()->after('balance_after');
        });
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropColumn('transaction_date');
        });
    }
};
