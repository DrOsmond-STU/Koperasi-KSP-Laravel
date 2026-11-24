<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Denda (penalty) sekarang jadi komponen ke-3 yang staf bisa isi
     * sendiri di form Catat Angsuran (lihat LoanRepaymentService::
     * recordManualPayment(), 26 Agu 2026) — sebelumnya loan_repayments cuma
     * punya principal_portion + interest_portion, tidak ada tempat mencatat
     * porsi denda sama sekali. default(0) supaya baris lama (semua denda=0
     * karena kolom ini belum ada) tetap valid.
     */
    public function up(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->decimal('penalty_portion', 18, 2)->default(0)->after('interest_portion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropColumn('penalty_portion');
        });
    }
};
