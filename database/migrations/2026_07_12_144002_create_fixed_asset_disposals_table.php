<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pelepasan Aktiva Tetap (PRD §14.2, DEP-05). `gain_loss_amount` is
     * signed: positive = Laba Pelepasan (4903), negative = Rugi Pelepasan
     * (5904). One row per asset — a disposed asset's `fixed_assets.status`
     * moves off `aktif`, which is what excludes it from the next
     * DepreciationEngine batch (no separate "excluded" flag needed).
     */
    public function up(): void
    {
        Schema::create('fixed_asset_disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->restrictOnDelete();
            $table->enum('disposal_type', ['dijual', 'dihapusbukukan', 'hilang']);
            $table->decimal('sale_amount', 18, 2)->default(0);
            $table->decimal('book_value_at_disposal', 18, 2);
            $table->decimal('gain_loss_amount', 18, 2);
            $table->date('disposal_date');
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_disposals');
    }
};
