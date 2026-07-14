<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * AUD-05: lock migrasi saldo awal wajib mencatat hasil rekonsiliasi
     * saat lock (bukan hanya siapa & kapan). Disimpan di kolom ini lalu
     * di-set bersamaan dengan `status`/`locked_by`/`locked_at` di
     * OpeningBalanceLockService — Auditable trait yang sudah ada di
     * OpeningBalanceBatch otomatis merekam before->after-nya, tidak perlu
     * logika audit khusus baru.
     */
    public function up(): void
    {
        Schema::table('opening_balance_batches', function (Blueprint $table) {
            $table->json('reconciliation_snapshot')->nullable()->after('locked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('opening_balance_batches', function (Blueprint $table) {
            $table->dropColumn('reconciliation_snapshot');
        });
    }
};
