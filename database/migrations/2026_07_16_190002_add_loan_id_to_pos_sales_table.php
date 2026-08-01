<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One-directional link only (pos_sales.loan_id) — same precedent as the
     * existing nullable savings_account_id FK on this table. Set when
     * payment_method = 'hutang' (PosSaleService::sell()).
     */
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->foreignId('loan_id')->nullable()->after('savings_account_id')
                ->constrained('loans')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_id');
        });
    }
};
