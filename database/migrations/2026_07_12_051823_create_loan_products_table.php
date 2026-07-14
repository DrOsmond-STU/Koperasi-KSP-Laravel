<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master data dinamis (PRD §7.3). `approval_threshold`: pengajuan dengan
     * `principal_amount` di atas ambang ini butuh 2 approval berbeda orang,
     * di bawah/sama dengan ambang cukup 1 (skema jenjang sederhana — lihat
     * LoanApprovalService). `coa_*` wajib diisi (AUTH-14) sebelum produk
     * dapat `is_active`.
     */
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('min_plafon', 18, 2);
            $table->decimal('max_plafon', 18, 2);
            $table->unsignedSmallInteger('min_tenor_months');
            $table->unsignedSmallInteger('max_tenor_months');
            $table->enum('calculation_method', ['flat', 'efektif', 'anuitas']);
            $table->decimal('provision_fee_percentage', 5, 2)->default(0);
            $table->decimal('penalty_percentage_per_day', 5, 3)->default(0);
            $table->decimal('approval_threshold', 18, 2)->nullable();
            $table->foreignId('coa_receivable_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('coa_interest_income_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('coa_provision_income_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('coa_penalty_receivable_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
