<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pembatalan transaksi simpanan yang sudah diposting selalu lewat
     * SavingsService::reverseTransaction() — baris asli TIDAK PERNAH
     * dihapus/diubah nilainya, hanya ditandai dibatalkan + ditaut ke jurnal
     * pembalik (JournalEngine::reverse()).
     */
    public function up(): void
    {
        Schema::table('savings_transactions', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('description');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->restrictOnDelete();
            $table->string('cancellation_reason')->nullable()->after('cancelled_by');
            $table->foreignId('reversal_journal_entry_id')->nullable()->after('cancellation_reason')->constrained('journal_entries')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('savings_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('reversal_journal_entry_id');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });
    }
};
