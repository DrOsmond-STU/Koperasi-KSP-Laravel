<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Laporan wajib regulator (Neraca/Laba Rugi/Arus Kas/CALK) — deliberately
     * a SEPARATE table from `report_exports` (Report Builder kustom), per
     * PRD §17: "laporan wajib regulator tetap punya format tetap tersendiri,
     * terpisah dari laporan kustom". `requested_by`/`created_at` are what
     * satisfy RPT-07's "jejak dibuat oleh & waktu" on the exported file.
     */
    public function up(): void
    {
        Schema::create('financial_report_exports', function (Blueprint $table) {
            $table->id();
            $table->enum('report_kind', ['neraca', 'laba_rugi', 'arus_kas', 'calk']);
            $table->enum('basis', ['sak_ep', 'sak_emkm']);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('as_of_date')->nullable();
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
        Schema::dropIfExists('financial_report_exports');
    }
};
