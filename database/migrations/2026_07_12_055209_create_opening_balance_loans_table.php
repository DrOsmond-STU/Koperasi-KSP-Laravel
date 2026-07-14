<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Maps to saldo_awal_pinjaman.csv (01_PRD.md Lampiran B).
     * `external_loan_number` = no_pinjaman_lama (arsip sistem lama),
     * cross-referenced by opening_balance_installments within the same batch.
     */
    public function up(): void
    {
        Schema::create('opening_balance_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_batch_id')->constrained('opening_balance_batches')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('loan_product_id')->constrained('loan_products')->restrictOnDelete();
            $table->string('external_loan_number')->nullable();
            $table->date('disbursement_date');
            $table->decimal('original_principal', 18, 2);
            $table->decimal('outstanding_principal', 18, 2);
            $table->decimal('outstanding_interest', 18, 2)->default(0);
            $table->unsignedSmallInteger('tenor_months');
            $table->unsignedSmallInteger('remaining_tenor_months');
            $table->unsignedSmallInteger('next_installment_number');
            $table->date('next_due_date');
            $table->enum('collectibility', ['lancar', 'kurang_lancar', 'diragukan', 'macet'])->default('lancar');
            $table->timestamps();

            $table->index('opening_balance_batch_id', 'obl_batch_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_balance_loans');
    }
};
