<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master Alokasi SHU (dinamis, PRD §5.4/UU 25/1992) — persentase per
     * kategori (Cadangan, Jasa Anggota, Dana Pengurus, dst.) ditentukan
     * bebas oleh koperasi sesuai AD/ART, bukan hardcode. Jumlah seluruh
     * `percentage` aktif harus 100% (ditegakkan di ShuService, bukan di
     * skema). `is_jasa_anggota` menandai SATU kategori (biasanya) sebagai
     * yang dipecah lebih lanjut per anggota berdasarkan saldo simpanan.
     */
    public function up(): void
    {
        Schema::create('shu_allocation_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('percentage', 5, 2);
            $table->boolean('is_jasa_anggota')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shu_allocation_categories');
    }
};
