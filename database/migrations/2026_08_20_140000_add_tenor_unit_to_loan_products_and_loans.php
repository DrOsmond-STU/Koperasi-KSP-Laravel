<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satuan tenor per produk pinjaman.
 *
 * Pinjaman Anggota ditagih harian — anggota pasar menyetor tiap hari — sedangkan
 * Pinjaman Karyawan dipotong gaji bulanan. Sebelumnya tenor selalu dianggap
 * bulan, sehingga produk harian tidak bisa dibuat sama sekali.
 *
 * Satuannya menempel di produk, bukan diganti global di skema, karena kedua
 * satuan harus hidup berdampingan.
 *
 * Bawaannya 'bulan' supaya SELURUH baris yang sudah ada tetap berarti sama
 * persis seperti sebelumnya — termasuk 142 pinjaman hasil migrasi, yang tidak
 * boleh berubah maknanya.
 *
 * loans ikut menyimpan satuannya sendiri, sejalan dengan interest_rate_percentage
 * yang juga di-snapshot saat pengajuan: kalau produknya kelak diubah, pinjaman
 * yang terlanjur berjalan tetap terbaca dengan satuan saat ia dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->enum('tenor_unit', ['hari', 'bulan'])
                ->default('bulan')
                ->after('max_tenor_months');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->enum('tenor_unit', ['hari', 'bulan'])
                ->default('bulan')
                ->after('tenor_months');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('tenor_unit');
        });

        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn('tenor_unit');
        });
    }
};
