<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master Kegiatan Koperasi (dinamis, PRD §15.1) — sumber manual
     * Dashboard Kalender. `branch_scope_type`/`single_branch_id` mirrors
     * `user_branch_scope`'s single/multiple/all pattern (see
     * `cooperative_event_branch` for "multiple"). `participant_type`
     * decides which of `cooperative_event_members`/`cooperative_event_roles`
     * applies — "semua_anggota_cabang" needs no pivot rows.
     * `reminder_days` is a small JSON array of day-offsets (e.g. [3,1,0]
     * for H-3/H-1/hari-H) — a dedicated table would be overkill for what
     * is always a handful of small integers.
     */
    public function up(): void
    {
        Schema::create('cooperative_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('location')->nullable();
            $table->enum('branch_scope_type', ['single', 'multiple', 'all']);
            $table->foreignId('single_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->enum('participant_type', ['anggota_tertentu', 'role_tertentu', 'semua_anggota_cabang']);
            $table->json('reminder_days')->nullable();
            $table->enum('status', ['draft', 'terjadwal', 'selesai', 'dibatalkan'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cooperative_events');
    }
};
