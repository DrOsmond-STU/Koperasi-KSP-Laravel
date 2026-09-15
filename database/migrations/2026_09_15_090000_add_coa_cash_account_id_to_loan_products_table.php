<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sisi kas jurnal pencairan sebelumnya dipatok ke kode akun '1101' di dalam
 * LoanApprovalService. Koperasi yang memakai bagan akunnya sendiri (akun kas
 * riilnya bukan 1101, dan 1101 dijadikan akun header) membuat setiap
 * persetujuan pinjaman gagal posting. Akun kas kini bisa ditetapkan per
 * produk pinjaman — sejajar dengan akun piutang/bunga/provisi/denda yang
 * sudah ada — sehingga produk KSP, USP, dan UPF bisa mencairkan dari akun
 * kasnya masing-masing. Dibiarkan nullable: yang kosong tetap jatuh ke '1101'
 * seperti perilaku lama, jadi instalasi bawaan tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->foreignId('coa_cash_account_id')
                ->nullable()
                ->after('coa_penalty_receivable_account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coa_cash_account_id');
        });
    }
};
