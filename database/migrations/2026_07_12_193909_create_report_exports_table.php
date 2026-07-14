<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PRD §17 — "untuk dataset besar, generate laporan berjalan async
     * dengan notifikasi selesai" (RPT-12). Every export goes through the
     * queue regardless of size, uniformly; `columns`/`filters`/`report_type`
     * are snapshotted here so a later template edit never changes an
     * export already in flight or already completed.
     */
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_template_id')->nullable()->constrained('report_templates')->nullOnDelete();
            $table->string('report_type');
            $table->json('columns');
            $table->json('filters')->nullable();
            $table->enum('format', ['pdf', 'xlsx']);
            $table->enum('status', ['menunggu', 'memproses', 'selesai', 'gagal'])->default('menunggu');
            $table->string('file_path')->nullable();
            $table->string('error_message')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
