<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ambang nilai global per modul (PRD §13.4/§14.2, "approval berjenjang
     * di atas ambang nilai"). Tidak ada baris untuk sebuah modul == ambang
     * dianggap 0 (fail-safe: semua transaksi butuh approval sampai
     * dikonfigurasi eksplisit) — lihat ApprovalThreshold::forModule().
     */
    public function up(): void
    {
        Schema::create('approval_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('module')->unique();
            $table->decimal('threshold_amount', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_thresholds');
    }
};
