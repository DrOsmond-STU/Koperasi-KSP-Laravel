<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Maps to saldo_awal_angsuran.csv (01_PRD.md Lampiran B) — one row per
     * remaining installment of an opening_balance_loans row (OB-09: must
     * cross-reference an existing loan in the same batch).
     */
    public function up(): void
    {
        Schema::create('opening_balance_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_batch_id')->constrained('opening_balance_batches')->cascadeOnDelete();
            $table->foreignId('opening_balance_loan_id')->constrained('opening_balance_loans')->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date');
            $table->decimal('principal_amount', 18, 2);
            $table->decimal('interest_amount', 18, 2);
            $table->decimal('penalty_amount', 18, 2)->default(0);
            $table->enum('status', ['belum_bayar', 'sebagian'])->default('belum_bayar');
            $table->timestamps();

            $table->unique(['opening_balance_loan_id', 'installment_number'], 'obi_loan_installment_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_balance_installments');
    }
};
