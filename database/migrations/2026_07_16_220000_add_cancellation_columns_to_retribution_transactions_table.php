<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Pola sama seperti savings_transactions — lihat
     * migrasi 2026_07_16_210000.
     */
    public function up(): void
    {
        Schema::table('retribution_transactions', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('created_by');
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
        Schema::table('retribution_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('reversal_journal_entry_id');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });
    }
};
