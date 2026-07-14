<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One batch per branch migration effort (PRD §9). While `status = draft`
     * every sub-modul row below can be freely edited/re-imported; once
     * `locked` (OpeningBalanceLockService), nothing under this batch may be
     * changed — corrections only via journal adjustment afterwards.
     */
    public function up(): void
    {
        Schema::create('opening_balance_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->date('cutoff_date');
            $table->enum('status', ['draft', 'locked'])->default('draft');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_balance_batches');
    }
};
