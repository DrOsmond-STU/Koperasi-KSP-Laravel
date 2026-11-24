<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ubah semantik tenor pinjaman dari BULANAN ke HARIAN untuk seluruh
 * sistem. Kolom `tenor_months` (dan variannya di tabel opening balance)
 * di-rename ke `tenor_days` supaya nama kolom konsisten dengan makna
 * baru — angka yang tersimpan tetap sama, tapi maknanya sekarang HARI,
 * bukan BULAN. Data lama (kalau ada) yang menyimpan tenor "12" akan
 * dibaca sebagai "12 hari" — sesuai keinginan desain (semua pinjaman
 * ke depan bermodel harian ala koperasi harian/pasar). Tipe kolom
 * `unsignedSmallInteger` (max 65 535) tetap cukup untuk tenor harian
 * hingga puluhan tahun sehingga tidak perlu perubahan tipe.
 *
 * `penalty_percentage_per_day` (denda per hari) memang sudah harian
 * sejak awal — tidak disentuh oleh migrasi ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->renameColumn('min_tenor_months', 'min_tenor_days');
            $table->renameColumn('max_tenor_months', 'max_tenor_days');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->renameColumn('tenor_months', 'tenor_days');
        });

        Schema::table('opening_balance_loans', function (Blueprint $table) {
            $table->renameColumn('tenor_months', 'tenor_days');
            $table->renameColumn('remaining_tenor_months', 'remaining_tenor_days');
        });
    }

    public function down(): void
    {
        Schema::table('opening_balance_loans', function (Blueprint $table) {
            $table->renameColumn('remaining_tenor_days', 'remaining_tenor_months');
            $table->renameColumn('tenor_days', 'tenor_months');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->renameColumn('tenor_days', 'tenor_months');
        });

        Schema::table('loan_products', function (Blueprint $table) {
            $table->renameColumn('max_tenor_days', 'max_tenor_months');
            $table->renameColumn('min_tenor_days', 'min_tenor_months');
        });
    }
};
