<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE loans MODIFY status ENUM('diajukan', 'disetujui', 'ditolak', 'dicairkan', 'lunas', 'dibatalkan') NOT NULL DEFAULT 'diajukan'");

        Schema::table('loans', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('disbursed_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->restrictOnDelete();
            $table->string('cancellation_reason')->nullable()->after('cancelled_by');
            $table->foreignId('reversal_journal_entry_id')->nullable()->after('cancellation_reason')->constrained('journal_entries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('reversal_journal_entry_id');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });

        DB::statement("ALTER TABLE loans MODIFY status ENUM('diajukan', 'disetujui', 'ditolak', 'dicairkan', 'lunas') NOT NULL DEFAULT 'diajukan'");
    }
};
