<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master Kategori Aktiva Tetap (dinamis, PRD §14.1) — mis. Tanah,
     * Bangunan, Kendaraan, Peralatan Kantor, Inventaris. Defaults here can
     * be overridden per-asset on `fixed_assets`. `coa_*` wajib diisi
     * (AUTH-14) sebelum kategori bisa `is_active`.
     */
    public function up(): void
    {
        Schema::create('fixed_asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('default_depreciation_method', ['garis_lurus', 'saldo_menurun']);
            $table->unsignedSmallInteger('default_useful_life_months');
            $table->foreignId('coa_asset_account_id')->nullable();
            $table->foreignId('coa_accumulated_depreciation_account_id')->nullable();
            $table->foreignId('coa_depreciation_expense_account_id')->nullable();

            $table->foreign('coa_asset_account_id', 'fac_asset_account_fk')
                ->references('id')->on('chart_of_accounts')->restrictOnDelete();
            $table->foreign('coa_accumulated_depreciation_account_id', 'fac_accum_dep_account_fk')
                ->references('id')->on('chart_of_accounts')->restrictOnDelete();
            $table->foreign('coa_depreciation_expense_account_id', 'fac_dep_expense_account_fk')
                ->references('id')->on('chart_of_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_categories');
    }
};
