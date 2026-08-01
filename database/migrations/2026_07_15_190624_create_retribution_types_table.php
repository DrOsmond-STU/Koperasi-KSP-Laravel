<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master Jenis Retribusi (dinamis) — modul Retribusi (UPF): satu setoran
     * total dipecah otomatis ke beberapa kategori sesuai `percentage`, lalu
     * dijurnal ke akun masing-masing. Persentase seluruh baris `is_active`
     * harus total tepat 100.00 (ditegakkan di RetributionService, sama pola
     * bccomp yang dipakai ShuService). `is_active` default false sampai
     * `coa_revenue_account_id` di-link — sama pola fee_types dulu.
     */
    public function up(): void
    {
        Schema::create('retribution_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('percentage', 5, 2);
            $table->foreignId('coa_revenue_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retribution_types');
    }
};
