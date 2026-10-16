<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan staf 26 Agu 2026: form Setor/Tarik di /staf/teller tidak punya
 * field tanggal sama sekali — deposit()/withdraw() diam-diam selalu
 * memakai now(), padahal banyak transaksi lama (belum sempat dicatat)
 * perlu disusulkan dengan tanggal aslinya. Sama pola dengan
 * loan_repayments.paid_at / retribution_transactions.transaction_date.
 * Nullable karena baris lama di produksi tidak punya nilai ini — lihat
 * SavingsTransaction::transactionOn() untuk fallback tampilannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_transactions', function (Blueprint $table) {
            $table->date('transaction_date')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('savings_transactions', function (Blueprint $table) {
            $table->dropColumn('transaction_date');
        });
    }
};
