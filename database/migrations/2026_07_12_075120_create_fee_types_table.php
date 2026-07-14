<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master Jenis Iuran (dinamis, PRD §10) — mis. Kebersihan (flat),
     * Listrik/Air (per meter). `coa_receivable_account_id`/`coa_revenue_account_id`
     * wajib diisi (AUTH-14) sebelum jenis iuran bisa `is_active`.
     */
    public function up(): void
    {
        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('unit_type', ['flat', 'per_meter', 'per_m2']);
            $table->decimal('tariff', 18, 2);
            $table->enum('billing_period', ['bulanan', 'mingguan'])->default('bulanan');
            $table->foreignId('coa_receivable_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('coa_revenue_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_types');
    }
};
