<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `1101` ("Kas") — akun konsolidasi generik — dipakai sebagai fallback
     * hardcode di banyak service (lihat ChartOfAccount::PROTECTED_CODES).
     * Untuk transaksi yang benar-benar terikat cabang (mis. angsuran
     * pinjaman yang diterima Teller di cabang tertentu), koperasi ini sudah
     * punya akun kas per unit/AO sendiri di Bagan Akun (KAS KECIL (KSP),
     * KAS AO ... (USP), dst.) — kolom ini memungkinkan tiap cabang
     * dipetakan ke akun kasnya masing-masing. Nullable: cabang yang belum
     * diisi tetap jatuh ke fallback `1101` di kode (tidak ada yang patah).
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('cash_account_id')->nullable()->after('parent_branch_id')
                ->constrained('chart_of_accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_account_id');
        });
    }
};
