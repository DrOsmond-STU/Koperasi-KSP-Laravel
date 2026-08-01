<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Portal Anggota's "Setor Simpanan Mandiri" — member is redirected to a
     * Xendit-hosted Invoice checkout page; a webhook (never the browser
     * redirect) confirms payment and triggers SavingsService::deposit(),
     * linked back here via savings_transaction_id. No staff approval step —
     * unlike a withdrawal, money coming IN is trusted once Xendit confirms it.
     */
    public function up(): void
    {
        Schema::create('savings_deposit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('savings_account_id')->constrained('savings_accounts')->restrictOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->enum('status', ['menunggu_pembayaran', 'dibayar', 'kedaluwarsa', 'gagal', 'dibatalkan'])->default('menunggu_pembayaran');
            $table->string('external_id')->unique();
            $table->string('xendit_invoice_id')->nullable();
            $table->string('xendit_invoice_url')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
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
        Schema::dropIfExists('savings_deposit_requests');
    }
};
