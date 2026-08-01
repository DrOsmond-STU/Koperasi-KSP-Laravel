<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Portal Anggota's "Pengajuan Penarikan Simpanan" — a member requests a
     * withdrawal, staff (teller/manajer) reviews it. Approving calls the
     * existing SavingsService::withdraw() (never writes savings_transactions
     * directly here) and links the resulting row via savings_transaction_id.
     * No self-service money movement — third-party payment gateway
     * integration (Xendit etc.) is out of scope for now.
     */
    public function up(): void
    {
        Schema::create('savings_withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('savings_account_id')->constrained('savings_accounts')->restrictOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->enum('status', ['menunggu', 'disetujui', 'ditolak'])->default('menunggu');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('decision_notes')->nullable();
            $table->foreignId('savings_transaction_id')->nullable()->constrained('savings_transactions')->restrictOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('savings_withdrawal_requests');
    }
};
