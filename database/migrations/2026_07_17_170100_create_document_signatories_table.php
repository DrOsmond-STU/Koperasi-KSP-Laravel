<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A reusable roster of named sign-offs (e.g. "Budi Santoso — Ketua
     * Koperasi") admin manages here and assigns into document_signature_slots.
     * Deliberately NOT tied to `users`/RBAC — the cooperative's chairman and
     * board ("Pengurus"/"Ketua Koperasi") often have no login account at
     * all; they sign printed paper, not the app.
     */
    public function up(): void
    {
        Schema::create('document_signatories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_signatories');
    }
};
