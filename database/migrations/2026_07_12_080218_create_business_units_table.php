<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master Unit Usaha Lain (dinamis, PRD §12) — di luar USP/Toko/UPF yang
     * sudah punya modul sendiri. `coa_*` wajib diisi (AUTH-14).
     */
    public function up(): void
    {
        Schema::create('business_units', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('coa_revenue_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('coa_expense_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_units');
    }
};
