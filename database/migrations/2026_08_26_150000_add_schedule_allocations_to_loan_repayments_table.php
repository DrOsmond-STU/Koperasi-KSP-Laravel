<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan staf 26 Agu 2026: "kalau ada edit atau delete angsuran, jurnal
 * COA harus di-update juga" — perlu tahu PERSIS baris loan_schedules mana
 * yang disentuh satu LoanRepayment dan berapa bagiannya, supaya pembatalan
 * (LoanRepaymentService::reverseRepayment()) bisa mengembalikan
 * paid_principal_amount/paid_interest_amount/paid_amount TEPAT ke nilai
 * sebelum pembayaran itu — apa pun urutan pembatalannya nanti (satu
 * pinjaman bisa dibayar berkali-kali menyentuh banyak baris jadwal
 * sekaligus, dan baris `loans_schedules` sendiri tidak menyimpan riwayat
 * per-pembayaran). Array of {schedule_id, principal_share, interest_share}
 * — sama pola JSON audit trail dengan audit_logs.before/after.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->json('schedule_allocations')->nullable()->after('penalty_portion');
        });
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropColumn('schedule_allocations');
        });
    }
};
