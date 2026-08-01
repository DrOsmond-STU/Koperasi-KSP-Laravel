<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Snapshot per kategori dari hasil pemecahan satu retribution_transaction
     * (nama & persentase & akun COA disalin apa adanya SAAT transaksi
     * diposting) — laporan historis harus tetap akurat walau
     * retribution_types diedit belakangan, sama alasannya kenapa
     * journal_lines tidak pernah di-derive ulang dari sumber yang mutable.
     */
    public function up(): void
    {
        Schema::create('retribution_transaction_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retribution_transaction_id')->constrained('retribution_transactions')->cascadeOnDelete();
            $table->foreignId('retribution_type_id')->constrained('retribution_types')->restrictOnDelete();
            $table->string('retribution_type_name');
            $table->decimal('percentage_applied', 5, 2);
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retribution_transaction_lines');
    }
};
