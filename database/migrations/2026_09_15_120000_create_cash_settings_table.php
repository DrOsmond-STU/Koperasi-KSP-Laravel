<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setelan kas yang berlaku untuk seluruh koperasi (bukan per cabang).
 *
 * Yang pertama ditampung: akun kas sumber PENCAIRAN pinjaman. Akun ini tidak
 * bisa diturunkan dari cabang si pinjaman karena dua hal:
 *
 * - `loans.branch_id` tidak bisa dipercaya sebagai penunjuk unit. Temuan 25
 *   Agu 2026: 142 pinjaman aktif seluruhnya tersimpan di cabang root "KPPD
 *   Pusat", peninggalan data lama — alasan yang sama yang membuat
 *   LoanRepaymentService::defaultCashAccount() mencari cabang lewat nama.
 * - Uang pencairan keluar dari kas kecil unit, sementara angsurannya masuk
 *   lewat kas AO. Keduanya memang akun berbeda, jadi akun kas cabang
 *   (branches.cash_account_id) yang dipakai angsuran bukan jawaban untuk
 *   pencairan.
 *
 * Nullable: selama belum diisi, pencairan tetap memakai resolusi lama (akun
 * kas cabang, lalu akun kas bawaan) — instalasi bawaan tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_disbursement_account_id')->nullable()
                ->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_settings');
    }
};
