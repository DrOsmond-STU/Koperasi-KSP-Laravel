<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tarif bunga tahunan per rentang tanggal berlaku — tidak retroaktif
     * (PRD §2.4). Setiap pengajuan pinjaman men-snapshot `interest_rate_percentage`
     * ke tabel `loans` saat diajukan, jadi pinjaman berjalan tidak berubah
     * walau tarif produk berubah kemudian.
     */
    public function up(): void
    {
        Schema::create('loan_product_rate_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_product_id')->constrained('loan_products')->cascadeOnDelete();
            $table->decimal('rate_percentage', 6, 3);
            $table->date('effective_from');
            $table->timestamps();

            $table->index(['loan_product_id', 'effective_from'], 'lprh_product_effective_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_product_rate_history');
    }
};
