<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PRD §13.3. No approval workflow (unlike Pembelian/Koreksi/Aktiva
     * Tetap) — posts stock + journal immediately, same as Kas Teller.
     * `savings_account_id` is only set when `payment_method = potong_simpanan`.
     */
    public function up(): void
    {
        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('savings_account_id')->nullable()->constrained('savings_accounts')->restrictOnDelete();
            $table->string('sale_number')->unique();
            $table->date('sale_date');
            $table->enum('payment_method', ['tunai', 'potong_simpanan']);
            $table->decimal('total_amount', 18, 2);
            $table->decimal('total_cogs', 18, 2);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'sale_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sales');
    }
};
