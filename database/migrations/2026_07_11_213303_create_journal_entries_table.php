<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Header of a double-entry journal entry. Append-only — the JournalEngine
     * (app/Services/Accounting/JournalEngine.php) is the only writer; rows
     * are never updated/deleted, corrections are posted as a new reversing
     * entry referencing `reversal_of_entry_id` (ARCHITECTURE §3.2/§4).
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->date('entry_date');
            $table->string('description');
            // Polymorphic reference to the transaction that produced this
            // entry (SavingsTransaction, LoanTransaction, PosSale, etc.).
            $table->nullableMorphs('source');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reversal_of_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'entry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
