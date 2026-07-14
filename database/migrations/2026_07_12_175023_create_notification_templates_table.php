<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PRD §16 — template pesan per (trigger_type, channel) dengan variabel
     * placeholder ({nama_anggota}, {no_pinjaman}, ...) diganti lewat
     * `strtr()` (NotificationTemplateResolver), bukan `eval()`. Admin bisa
     * mengubah `body`/`subject` tanpa perubahan kode (NOTIF-07) — notifikasi
     * yang sudah tercatat di `notification_logs` menyimpan pesan versi
     * jadi-nya sendiri, jadi tidak berubah retroaktif.
     */
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('trigger_type');
            $table->enum('channel', ['whatsapp', 'email']);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['trigger_type', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
