<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Satu setoran retribusi total (modul Retribusi/UPF, PRD §6: cabang
     * wajib di setiap transaksi) — pemecahan ke tiap kategori dicatat di
     * `retribution_transaction_lines`, jurnalnya di `journal_entries` lewat
     * JournalEngine (single writer, tidak ada posting lain di luar itu).
     */
    public function up(): void
    {
        Schema::create('retribution_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('transaction_number')->unique();
            $table->date('transaction_date');
            $table->enum('payer_type', ['umum', 'anggota']);
            $table->string('payer_name');
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->decimal('total_amount', 18, 2);
            $table->enum('payment_method', ['tunai', 'transfer'])->default('tunai');
            $table->string('description')->nullable();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retribution_transactions');
    }
};
