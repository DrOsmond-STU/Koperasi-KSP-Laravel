<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Native MySQL enum — extended via raw ALTER (no doctrine/dbal
     * dependency required, unlike Schema::table()->change()). Adds
     * 'saldo_awal' so OpeningBalanceLockService::materializeStock() can tag
     * the opening stock-ledger row distinctly from real pembelian/penjualan
     * mutations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE stock_ledger MODIFY transaction_type ENUM('pembelian', 'penjualan', 'retur_pembelian', 'retur_penjualan', 'koreksi_plus', 'koreksi_minus', 'saldo_awal') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE stock_ledger MODIFY transaction_type ENUM('pembelian', 'penjualan', 'retur_pembelian', 'retur_penjualan', 'koreksi_plus', 'koreksi_minus') NOT NULL");
    }
};
