<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom `retribution_type_id` untuk mencatat jenis retribusi yang
     * DIPILIH secara eksplisit saat transaksi UMUM — nullable karena
     * transaksi ANGGOTA di-split otomatis ke seluruh jenis "split"
     * (persentase < 100) dan tidak menunjuk ke satu jenis tunggal.
     *
     * Untuk transaksi UMUM baris `retribution_transaction_lines` akan
     * berisi tepat satu baris dengan `percentage_applied = 100.00` yang
     * mengarah ke jenis yang sama; kolom ini hanya shortcut baca
     * (mempermudah query "semua transaksi umum jenis X").
     */
    public function up(): void
    {
        Schema::table('retribution_transactions', function (Blueprint $table) {
            $table->foreignId('retribution_type_id')
                ->nullable()
                ->after('member_id')
                ->constrained('retribution_types')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('retribution_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('retribution_type_id');
        });
    }
};
