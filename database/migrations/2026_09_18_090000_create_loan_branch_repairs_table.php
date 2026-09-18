<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak setiap kali pengurus memindahkan kegiatan pinjaman ke satu cabang.
 *
 * Pemindahan ini menyentuh jurnal yang sudah diposting. Nilai debet/kredit,
 * akun, dan tanggalnya tidak ikut berubah — hanya kolom branch_id — tapi
 * pembukuan append-only tetap menuntut jejak: siapa, kapan, berapa baris,
 * dan dari cabang mana masing-masing baris berasal.
 *
 * `payload` menyimpan cabang LAMA setiap baris, jadi pembatalannya tidak
 * perlu menebak: ia mengembalikan tiap baris ke nilainya sendiri, bukan ke
 * satu cabang seragam. Itu penting karena baris yang dipindah bisa berasal
 * dari cabang yang berbeda-beda (di produksi 18 Sep 2026: pinjaman POS ada
 * yang dari Pusat dan ada yang dari KSP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_branch_repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('performed_by')->constrained('users');
            $table->unsignedInteger('loans_moved')->default(0);
            $table->unsignedInteger('repayments_moved')->default(0);
            $table->unsignedInteger('entries_moved')->default(0);
            $table->json('payload');
            $table->timestamp('reverted_at')->nullable();
            $table->foreignId('reverted_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_branch_repairs');
    }
};
