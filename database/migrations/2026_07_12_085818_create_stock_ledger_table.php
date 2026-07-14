<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Single writer table for stock mutation (StockLedgerEngine, PRD §13.7
     * "KartuPersediaan"). `running_qty`/`running_avg_cost` are the balance
     * AFTER this row, per (product, branch) — mirrors how journal_lines is
     * the source of truth for account balances rather than a denormalized
     * column on chart_of_accounts. `source_type`/`source_id` reference the
     * originating transaction (purchase, POS sale, return, adjustment).
     */
    public function up(): void
    {
        Schema::create('stock_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->date('transaction_date');
            $table->enum('transaction_type', [
                'pembelian',
                'penjualan',
                'retur_pembelian',
                'retur_penjualan',
                'koreksi_plus',
                'koreksi_minus',
            ]);
            $table->nullableMorphs('source');
            $table->decimal('qty_in', 18, 4)->default(0);
            $table->decimal('qty_out', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('running_qty', 18, 4);
            $table->decimal('running_avg_cost', 18, 4);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'branch_id', 'id'], 'stock_ledger_product_branch_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_ledger');
    }
};
