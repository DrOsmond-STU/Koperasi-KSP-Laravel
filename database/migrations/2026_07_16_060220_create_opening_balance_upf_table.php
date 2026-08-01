<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Saldo Awal UPF (v2, pasca-Retribusi): bukan lagi piutang per-kios
     * per-jenis-iuran (model lama, sudah dihapus bersama FeeType/Tenant) —
     * sekarang saldo pendapatan awal per RetributionType aktif, dibawa dari
     * sistem lama. CSV: kode_jenis_retribusi, saldo_awal, keterangan.
     */
    public function up(): void
    {
        Schema::create('opening_balance_upf', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_batch_id')->constrained('opening_balance_batches')->cascadeOnDelete();
            $table->foreignId('retribution_type_id')->constrained('retribution_types')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('opening_balance_batch_id', 'obu_batch_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_balance_upf');
    }
};
