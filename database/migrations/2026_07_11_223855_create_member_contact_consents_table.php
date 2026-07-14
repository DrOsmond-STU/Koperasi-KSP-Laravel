<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Consent PDP per channel (PRD §16.3, SECURITY.md §PII handling): opt-in
     * saat pendaftaran, anggota bisa menarik consent per channel kapan saja.
     */
    public function up(): void
    {
        Schema::create('member_contact_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->enum('channel', ['whatsapp', 'email']);
            $table->boolean('consented')->default(false);
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_contact_consents');
    }
};
