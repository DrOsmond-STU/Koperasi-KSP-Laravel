<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Native MySQL enum — extended via raw ALTER (no doctrine/dbal
     * dependency required, unlike Schema::table()->change()).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE pos_sales MODIFY payment_method ENUM('tunai', 'potong_simpanan', 'hutang') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE pos_sales MODIFY payment_method ENUM('tunai', 'potong_simpanan') NOT NULL");
    }
};
