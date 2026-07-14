<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tarif per rentang tanggal berlaku (PRD §2.4 — "histori tarif tidak
     * retroaktif"): menambah baris baru dengan effective_from di masa depan
     * tidak mengubah perhitungan bunga transaksi yang sudah lewat, karena
     * perhitungan selalu memilih baris yang effective_from-nya <= tanggal
     * transaksi. `tiers` (JSON, dipakai bila interest_method = tiered) berisi
     * array {min_balance, max_balance, rate_percentage}; untuk metode lain
     * cukup `rate_percentage` tunggal.
     */
    public function up(): void
    {
        Schema::create('savings_product_rate_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_product_id')->constrained('savings_products')->cascadeOnDelete();
            $table->decimal('rate_percentage', 6, 3);
            $table->json('tiers')->nullable();
            $table->date('effective_from');
            $table->timestamps();

            $table->index(['savings_product_id', 'effective_from'], 'sprh_product_effective_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('savings_product_rate_history');
    }
};
