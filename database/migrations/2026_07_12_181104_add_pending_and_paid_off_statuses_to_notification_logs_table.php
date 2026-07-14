<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `menunggu`: the dedupe_key claim row created by CheckLoanDueDates
     * before the queued job actually attempts to send (this is what makes
     * NOTIF-01's "never dispatched twice" guarantee atomic — the claim
     * happens synchronously, sending happens later on the queue).
     * `dibatalkan_lunas`: NOTIF-02 — the installment was paid off between
     * being queued and being sent; distinct from `gagal` because nothing
     * actually went wrong, sending was correctly skipped.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE notification_logs MODIFY status ENUM('menunggu', 'terkirim', 'gagal', 'dibaca', 'tidak_dikirim_tanpa_consent', 'dibatalkan_lunas') NOT NULL DEFAULT 'menunggu'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE notification_logs MODIFY status ENUM('terkirim', 'gagal', 'dibaca', 'tidak_dikirim_tanpa_consent') NOT NULL DEFAULT 'gagal'");
    }
};
