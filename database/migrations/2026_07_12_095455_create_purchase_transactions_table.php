<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PRD §13.4. `status` tracks the approval workflow: below the
     * configured ambang nilai a purchase posts immediately (`diposting`
     * at creation); above it, it starts `menunggu_approval` and only
     * posts (stock + journal) once a DIFFERENT user approves it
     * (created_by <> approved_by, AUTH-09/AUTH-12). `payment_status`
     * tracks Hutang Usaha settlement independently of the approval
     * status — only meaningful when payment_method = kredit.
     */
    public function up(): void
    {
        Schema::create('purchase_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('purchase_number')->unique();
            $table->date('purchase_date');
            $table->enum('payment_method', ['tunai', 'kredit']);
            $table->decimal('total_amount', 18, 2);
            $table->enum('status', ['menunggu_approval', 'diposting', 'ditolak'])->default('menunggu_approval');
            $table->enum('payment_status', ['lunas', 'belum_lunas', 'sebagian'])->nullable();
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_transactions');
    }
};
