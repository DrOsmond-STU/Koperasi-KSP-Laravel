<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Portal Anggota's "Bayar Angsuran Mandiri" — gateway wiring on top of
     * LoanRepaymentService. `perlu_rekonsiliasi_manual` covers the rare race
     * where the outstanding amount changed between invoice creation and the
     * webhook arriving (e.g. another repayment landed in between), so
     * LoanRepaymentService::recordPayment() would now reject it as an
     * overpayment — flagged for staff review instead of mis-posting the ledger.
     */
    public function up(): void
    {
        Schema::create('loan_repayment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->enum('status', ['menunggu_pembayaran', 'dibayar', 'kedaluwarsa', 'gagal', 'perlu_rekonsiliasi_manual', 'dibatalkan'])->default('menunggu_pembayaran');
            $table->string('external_id')->unique();
            $table->string('xendit_invoice_id')->nullable();
            $table->string('xendit_invoice_url')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('loan_repayment_id')->nullable()->constrained('loan_repayments')->restrictOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_repayment_requests');
    }
};
