<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Pola sama seperti savings_transactions/
     * retribution_transactions — lihat migrasi 2026_07_16_210000. `status`
     * gains `dibatalkan` (native MySQL enum, raw ALTER) alongside the
     * existing menunggu_approval/diposting/ditolak.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE stock_adjustments MODIFY status ENUM('menunggu_approval', 'diposting', 'ditolak', 'dibatalkan') NOT NULL DEFAULT 'menunggu_approval'");

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('approved_by');
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
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('reversal_journal_entry_id');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });

        DB::statement("ALTER TABLE stock_adjustments MODIFY status ENUM('menunggu_approval', 'diposting', 'ditolak') NOT NULL DEFAULT 'menunggu_approval'");
    }
};
