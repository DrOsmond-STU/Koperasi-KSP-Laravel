<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Batch penyusutan bulanan (PRD §14.2, DepreciationEngine). One row
     * per (fixed_asset_id, period) — the unique index is what makes the
     * batch idempotent (DEP-02): re-running for an already-posted period
     * is a no-op for that asset, not a duplicate entry.
     */
    public function up(): void
    {
        Schema::create('fixed_asset_depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->restrictOnDelete();
            $table->string('period', 7); // 'YYYY-MM'
            $table->decimal('opening_book_value', 18, 2);
            $table->decimal('depreciation_amount', 18, 2);
            $table->decimal('closing_book_value', 18, 2);
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'period'], 'faded_asset_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_depreciation_entries');
    }
};
