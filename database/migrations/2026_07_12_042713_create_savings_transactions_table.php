<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every row is paired 1:1 with a journal_entries row created by
     * JournalEngine in the same DB transaction (SavingsService) — append-only.
     */
    public function up(): void
    {
        Schema::create('savings_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('savings_account_id')->constrained('savings_accounts')->restrictOnDelete();
            $table->enum('type', ['setor', 'tarik']);
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_after', 18, 2);
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('savings_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('savings_transactions');
    }
};
