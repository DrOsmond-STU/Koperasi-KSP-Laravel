<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gambar latar halaman login yang bisa diunggah sendiri oleh Admin
     * Sistem, lengkap dengan mode ukuran dan transparansinya — menumpang di
     * tabel singleton branding yang sudah ada, karena secara konsep ini
     * bagian dari identitas visual aplikasi yang sama (nama + logo).
     */
    public function up(): void
    {
        Schema::table('app_branding_settings', function (Blueprint $table) {
            $table->string('login_background_path')->nullable()->after('logo_path');

            // Mode ukuran: cover|contain|stretch|tile|custom — disimpan
            // sebagai string, bukan enum DB, supaya menambah mode baru nanti
            // tidak butuh migrasi ALTER yang mengunci tabel.
            $table->string('login_background_size', 20)->default('cover')->after('login_background_path');

            // Dipakai hanya saat mode = custom (persen dari lebar layar).
            $table->unsignedSmallInteger('login_background_scale')->default(100)->after('login_background_size');

            // 100 = gambar tampil penuh, 0 = tidak terlihat sama sekali.
            $table->unsignedTinyInteger('login_background_opacity')->default(100)->after('login_background_scale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_branding_settings', function (Blueprint $table) {
            $table->dropColumn([
                'login_background_path',
                'login_background_size',
                'login_background_scale',
                'login_background_opacity',
            ]);
        });
    }
};
