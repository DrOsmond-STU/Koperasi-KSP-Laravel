<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 08_TASK_INSTRUCTION.md 6.2 — journal_entries/journal_lines,
     * stock_ledger, dan fixed_asset_depreciation_entries sudah terindeks
     * sejak dibuat (Phase 1/3). Dua tabel di bawah tumbuh besar tapi belum
     * punya index pendukung query batch/laporan hariannya:
     * - loan_schedules: CheckLoanDueDates (4.3) query harian
     *   WHERE due_date = ? AND status != 'lunas'.
     * - notification_logs: dashboard log (NotificationLogController) hitung
     *   COUNT per status + `latest()->limit(100)`.
     */
    public function up(): void
    {
        Schema::table('loan_schedules', function (Blueprint $table) {
            $table->index(['due_date', 'status']);
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_schedules', function (Blueprint $table) {
            $table->dropIndex(['due_date', 'status']);
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });
    }
};
