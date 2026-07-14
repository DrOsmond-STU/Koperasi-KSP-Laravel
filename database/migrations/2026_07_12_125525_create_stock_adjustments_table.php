<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Koreksi Persediaan / Stock Opname (PRD §13.6). `system_qty`/
     * `physical_qty`/`variance_qty` are snapshotted at submission time (the
     * point-in-time opname record); `unit_cost`/`amount` are filled in when
     * actually posted (StockLedgerEngine's current average at that moment).
     * Selisih plus posts immediately; selisih minus ALWAYS needs approval
     * from someone other than the submitter (STK-05/06) — unlike Pembelian/
     * Aktiva Tetap this is unconditional, not threshold-based.
     */
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('stock_reason_id')->constrained('stock_reasons')->restrictOnDelete();
            $table->decimal('system_qty', 18, 4);
            $table->decimal('physical_qty', 18, 4);
            $table->decimal('variance_qty', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->enum('status', ['menunggu_approval', 'diposting', 'ditolak'])->default('menunggu_approval');
            $table->date('adjustment_date');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
