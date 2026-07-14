<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PRD §16 Log Notifikasi. `dedupe_key` is what makes NOTIF-01 hold —
     * e.g. "loan_schedule:{id}:offset:-3" — a unique constraint here means
     * a second dispatch attempt for the same (angsuran, aturan) combo can
     * never create a second row, mirroring JournalEngine's idempotency_key.
     * `message`/`subject` store the RENDERED text actually sent, so editing
     * a template later (NOTIF-07) never rewrites history.
     */
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key')->unique();
            $table->string('trigger_type');
            $table->enum('channel', ['whatsapp', 'email']);
            $table->nullableMorphs('notifiable');
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->enum('status', ['terkirim', 'gagal', 'dibaca', 'tidak_dikirim_tanpa_consent'])->default('gagal');
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
