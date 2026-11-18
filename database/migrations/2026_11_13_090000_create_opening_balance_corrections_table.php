<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Koreksi Saldo Awal Neraca (permintaan pemilik 13 Nov 2026).
     *
     * Tabel BARU — tidak mengubah tabel/data yang sudah ada. Satu baris =
     * satu koreksi atas saldo awal satu batch; angkanya tidak disimpan di
     * sini melainkan di jurnal koreksinya (journal_entry_id, source =
     * baris ini, bertanggal cut-off batch). opening_balance_coa dan jurnal
     * pembukaan tidak pernah disentuh. Membatalkan koreksi = membalik
     * jurnalnya (cancelled_at + reversal_journal_entry_id), tidak dihapus.
     */
    public function up(): void
    {
        Schema::create('opening_balance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_batch_id')->constrained('opening_balance_batches')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->string('reason', 255);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 255)->nullable();
            $table->foreignId('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->timestamps();

            $table->index('opening_balance_batch_id', 'obcorr_batch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_corrections');
    }
};
