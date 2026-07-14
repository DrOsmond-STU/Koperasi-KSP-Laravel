<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master data dinamis (PRD §7.1) — pengguna bebas menambah jenis anggota
     * sendiri (mis. Anggota Biasa, Calon Anggota, Anggota Luar Biasa).
     */
    public function up(): void
    {
        Schema::create('member_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('has_voting_rights')->default(true);
            $table->boolean('requires_mandatory_savings')->default(true);
            $table->decimal('mandatory_savings_default_amount', 18, 2)->nullable();
            $table->boolean('counts_toward_shu')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_types');
    }
};
