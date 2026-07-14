<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Maps to saldo_awal_coa.csv (01_PRD.md Lampiran B). Total debit must
     * equal total credit across a batch before it can be locked
     * (OpeningBalanceReconciliationService).
     */
    public function up(): void
    {
        Schema::create('opening_balance_coa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_batch_id')->constrained('opening_balance_batches')->cascadeOnDelete();
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->enum('position', ['debit', 'kredit']);
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->index('opening_balance_batch_id', 'obc_batch_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_balance_coa');
    }
};
