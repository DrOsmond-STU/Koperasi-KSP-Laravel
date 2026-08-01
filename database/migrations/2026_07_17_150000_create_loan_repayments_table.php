<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per payment EVENT against a loan (may cover several
     * installments in one lump sum) — LoanRepaymentService is the only
     * writer. Cancellation columns mirror SavingsTransaction's pattern
     * (append-only, void via reversal rather than delete/update).
     */
    public function up(): void
    {
        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->decimal('principal_portion', 18, 2);
            $table->decimal('interest_portion', 18, 2);
            $table->decimal('balance_after', 18, 2);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('description')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'loan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
    }
};
