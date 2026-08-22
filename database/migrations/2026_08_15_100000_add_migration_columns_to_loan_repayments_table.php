<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat pembayaran dari sistem lama perlu dua hal yang belum ada.
     *
     * `paid_at` — tanggal pembayaran yang sebenarnya. Tanpa kolom ini, 20.874
     * baris riwayat akan bersandar pada `created_at` dan seluruhnya tercatat
     * seolah dibayar pada hari import, membuat laporan per periode tidak ada
     * artinya. `created_at` tetap berarti "kapan baris ini dibuat" — dua
     * pertanyaan berbeda yang tidak boleh dijawab satu kolom.
     *
     * `migrated_at` — penanda asal. Non-null berarti baris ini dibawa dari
     * sistem lama, bukan transaksi yang terjadi di aplikasi ini. Tanpa
     * penanda, pembayaran migrasi tidak bisa dibedakan dari pembayaran asli,
     * dan setiap laporan pembayaran akan mencampur keduanya tanpa keterangan.
     *
     * Keduanya nullable supaya baris pembayaran yang sudah ada tidak tersentuh.
     */
    public function up(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->date('paid_at')->nullable()->after('balance_after');
            $table->timestamp('migrated_at')->nullable()->after('paid_at');

            $table->index('paid_at');
            $table->index('migrated_at');
        });
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
            $table->dropIndex(['migrated_at']);
            $table->dropColumn(['paid_at', 'migrated_at']);
        });
    }
};
