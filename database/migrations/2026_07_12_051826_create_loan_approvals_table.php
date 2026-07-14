<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per approval decision. Unique(loan_id, approved_by) so the
     * same person can't count twice toward `loans.required_approval_count`
     * (segregation of duties — a loan needing 2 approvals needs 2 *different*
     * people, not one person approving twice).
     */
    public function up(): void
    {
        Schema::create('loan_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->enum('decision', ['setuju', 'tolak']);
            $table->string('notes')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->unique(['loan_id', 'approved_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_approvals');
    }
};
