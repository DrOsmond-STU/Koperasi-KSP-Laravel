<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Singleton table (always exactly one row, id=1) — nama & logo aplikasi
     * yang bisa diubah bebas oleh koperasi (Admin Sistem), ditampilkan di
     * halaman login & topbar (03_DESIGN.md §Topbar).
     */
    public function up(): void
    {
        Schema::create('app_branding_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name');
            $table->string('logo_path')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_branding_settings');
    }
};
