<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PRD §14.2: metode Saldo Menurun butuh Tarif per aset (Nilai Buku
     * Berjalan × Tarif); tidak dipakai untuk Garis Lurus.
     */
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->decimal('depreciation_rate_percentage', 6, 3)->nullable()->after('depreciation_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn('depreciation_rate_percentage');
        });
    }
};
