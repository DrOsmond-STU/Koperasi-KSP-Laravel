<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user override untuk memaksa MFA aktif — dipakai Admin Sistem lewat form
 * Tambah/Ubah Pengguna. Role internal (teller, petugas_kredit, dsb.) tetap
 * wajib MFA lewat User::requiresMfa() apa pun nilai kolom ini; kolom hanya
 * relevan buat anggota, yang default-nya bebas — Admin bisa men-set true
 * bila ingin memaksa 2FA juga untuk akun anggota tertentu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('mfa_enforced')
                ->after('is_active')
                ->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mfa_enforced');
        });
    }
};
