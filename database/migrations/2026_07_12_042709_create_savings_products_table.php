<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master data dinamis (PRD §7.2) — koperasi bebas menambah produk
     * simpanan sendiri. `coa_liability_account_id`/`coa_interest_expense_account_id`
     * wajib diisi (ditegakkan di Form Request, AUTH-14) sebelum produk bisa
     * `is_active` dan dipakai transaksi.
     */
    public function up(): void
    {
        Schema::create('savings_products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('category', ['pokok', 'wajib', 'sukarela', 'berjangka']);
            $table->enum('interest_method', ['flat', 'saldo_harian', 'saldo_rata_rata_bulanan', 'tiered']);
            $table->decimal('minimum_initial_deposit', 18, 2)->default(0);
            $table->decimal('minimum_subsequent_deposit', 18, 2)->default(0);
            $table->decimal('admin_fee', 18, 2)->nullable();
            $table->enum('admin_fee_period', ['bulanan', 'tahunan'])->nullable();
            $table->decimal('withdrawal_penalty_percentage', 5, 2)->nullable();
            $table->foreignId('coa_liability_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('coa_interest_expense_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('savings_products');
    }
};
