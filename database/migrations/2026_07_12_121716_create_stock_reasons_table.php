<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master Alasan Mutasi Persediaan (dinamis, PRD §13.5/§13.6) — dipakai
     * bersama oleh Retur Pembelian dan Koreksi Persediaan (mis. Rusak,
     * Hilang, Kadaluarsa, Kesalahan Input, Retur ke Supplier).
     */
    public function up(): void
    {
        Schema::create('stock_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_reasons');
    }
};
