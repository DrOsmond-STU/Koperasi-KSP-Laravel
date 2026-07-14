<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Maps 1:1 to the CSV columns in 01_PRD.md Lampiran B
     * (saldo_awal_simpanan.csv): kode_anggota, kode_produk_simpanan,
     * no_rekening, saldo_awal, tanggal_saldo_awal, keterangan.
     */
    public function up(): void
    {
        Schema::create('opening_balance_savings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_batch_id')->constrained('opening_balance_batches')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('savings_product_id')->constrained('savings_products')->restrictOnDelete();
            $table->string('account_number')->nullable();
            $table->decimal('balance', 18, 2);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('opening_balance_batch_id', 'obs_batch_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_balance_savings');
    }
};
