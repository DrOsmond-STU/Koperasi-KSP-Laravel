<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `balance` is a cached running total, always written in the same
     * DB transaction as its savings_transactions row + JournalEngine post —
     * never updated independently (see SavingsService).
     */
    public function up(): void
    {
        Schema::create('savings_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('savings_product_id')->constrained('savings_products')->restrictOnDelete();
            $table->string('account_number')->unique();
            $table->decimal('balance', 18, 2)->default(0);
            $table->enum('status', ['aktif', 'ditutup'])->default('aktif');
            $table->date('opened_at');
            $table->date('closed_at')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'savings_product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('savings_accounts');
    }
};
