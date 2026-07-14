<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `interest_rate_percentage` dan `required_approval_count` di-snapshot
     * saat pengajuan (bukan dihitung ulang saat approve/cair) agar histori
     * tarif & skema approval yang berubah kemudian tidak memengaruhi
     * pinjaman yang sudah berjalan.
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('loan_product_id')->constrained('loan_products')->restrictOnDelete();
            $table->string('loan_number')->unique();
            $table->decimal('principal_amount', 18, 2);
            $table->unsignedSmallInteger('tenor_months');
            $table->decimal('interest_rate_percentage', 6, 3);
            $table->decimal('provision_fee_amount', 18, 2)->nullable();
            $table->unsignedTinyInteger('required_approval_count')->default(1);
            $table->enum('status', ['diajukan', 'disetujui', 'ditolak', 'dicairkan', 'lunas'])->default('diajukan');
            $table->enum('collectibility', ['lancar', 'kurang_lancar', 'diragukan', 'macet'])->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('submitted_at');
            $table->date('disbursed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
