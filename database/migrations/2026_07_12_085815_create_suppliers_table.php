<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master Supplier/Vendor (dinamis, PRD §13.2). Tidak ada kolom/route
     * delete secara desain — supplier yang sudah bertransaksi hanya bisa
     * dinonaktifkan (`is_active = false`), dijaga di Service layer (bukan
     * DB constraint) supaya pesan errornya jelas.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->enum('payment_term', ['tunai', 'kredit'])->default('tunai');
            $table->unsignedSmallInteger('payment_term_days')->nullable();
            $table->foreignId('coa_payable_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
