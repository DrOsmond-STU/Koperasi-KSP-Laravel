<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Koreksi Saldo Awal Neraca — riwayat setiap perubahan saldo awal akun
     * Neraca setelah batch migrasi dikunci. Baris `opening_balance_coa`
     * TIDAK pernah diubah setelah lock (RUNBOOK §5.6); selisihnya dicatat di
     * sini dan diposting sebagai jurnal koreksi lewat JournalEngine, sehingga
     * jejak audit "saldo lama → saldo baru" per akun tetap utuh.
     */
    public function up(): void
    {
        Schema::create('opening_balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_batch_id')
                ->constrained('opening_balance_batches', 'id', 'oba_batch_fk')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->date('entry_date');
            $table->text('reason');
            $table->decimal('total_debit', 18, 2)->default(0);
            $table->decimal('total_credit', 18, 2)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('opening_balance_batch_id', 'oba_batch_idx');
        });

        Schema::create('opening_balance_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_adjustment_id')
                ->constrained('opening_balance_adjustments', 'id', 'obal_adjustment_fk')->cascadeOnDelete();
            $table->foreignId('chart_of_account_id')
                ->constrained('chart_of_accounts', 'id', 'obal_account_fk')->restrictOnDelete();
            // Saldo dalam arah saldo normal akun (positif = sesuai saldo normal).
            $table->decimal('balance_before', 18, 2);
            $table->decimal('balance_after', 18, 2);
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->boolean('is_balancing')->default(false);
            $table->timestamps();

            $table->index('opening_balance_adjustment_id', 'obal_adjustment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_adjustment_lines');
        Schema::dropIfExists('opening_balance_adjustments');
    }
};
