<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One tagihan per kios per jenis iuran per periode. `journal_entry_id`
     * is the recognition entry (Dr Piutang Iuran, Cr Pendapatan Iuran)
     * posted by UpfService when the billing is generated.
     */
    public function up(): void
    {
        Schema::create('fee_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('fee_type_id')->constrained('fee_types')->restrictOnDelete();
            $table->string('period', 7); // format YYYY-MM
            $table->decimal('meter_start', 12, 2)->nullable();
            $table->decimal('meter_end', 12, 2)->nullable();
            $table->decimal('amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->enum('status', ['belum_bayar', 'sebagian', 'lunas'])->default('belum_bayar');
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'fee_type_id', 'period'], 'fee_billing_unique_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_billings');
    }
};
