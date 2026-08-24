<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pinjaman anggota koperasi ini HARIAN (mis. tenor 100 hari, 200 hari)
     * dengan jasa flat untuk seluruh tenor — bukan bulanan dengan tarif
     * per tahun seperti sebelumnya (laporan staf 24 Agu 2026: "perhitungan
     * jasa nya salah... sekarang yang ada adalah perhitungan nya bulan x
     * 12 jadi tahunan. padahal pinjaman anggota itu harian").
     *
     * Kolom *_tenor_days ini DITAMBAHKAN, bukan menggantikan *_tenor_months
     * — pinjaman yang sudah berjalan (status diajukan/disetujui/dicairkan)
     * sebelum perbaikan ini masih murni bulanan dan TIDAK disentuh/
     * diinterpretasi ulang; LoanApprovalService::disburse() tetap memakai
     * jalur bulanan lama untuk baris yang tenor_days-nya null. Produk &
     * pengajuan BARU dari sekarang selalu memakai tenor_days.
     */
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_tenor_days')->nullable()->after('max_tenor_months');
            $table->unsignedSmallInteger('max_tenor_days')->nullable()->after('min_tenor_days');
        });

        Schema::table('loan_products', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_tenor_months')->nullable()->change();
            $table->unsignedSmallInteger('max_tenor_months')->nullable()->change();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedSmallInteger('tenor_days')->nullable()->after('tenor_months');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedSmallInteger('tenor_months')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn(['min_tenor_days', 'max_tenor_days']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('tenor_days');
        });
    }
};
