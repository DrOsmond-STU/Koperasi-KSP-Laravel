<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak setiap kali pengurus menyelaraskan jadwal angsuran dengan buku besar
 * (lihat LoanScheduleAlignmentService).
 *
 * Yang diubah hanya kolom terbayar dan status pada loan_schedules, serta
 * status pinjaman — bukan jurnal. Tapi jadwal menentukan tagihan yang dilihat
 * staf dan anggota, jadi perubahannya tetap butuh jejak yang bisa ditarik
 * kembali: `payload` menyimpan nilai LAMA setiap baris jadwal dan status
 * lama tiap pinjaman, sehingga pembatalan mengembalikan persis keadaan
 * sebelumnya, bukan menghitung ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_schedule_alignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performed_by')->constrained('users');
            $table->unsignedInteger('loans_aligned')->default(0);
            $table->unsignedInteger('rows_changed')->default(0);
            $table->json('payload');
            $table->timestamp('reverted_at')->nullable();
            $table->foreignId('reverted_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_schedule_alignments');
    }
};
