<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PRD §14.1/§14.2. `status` covers both the acquisition approval
     * workflow (menunggu_approval/ditolak — created_by <> approved_by
     * above ambang nilai) and the operational lifecycle (aktif/dijual/
     * dihapusbukukan/nonaktif_sementara), same single-status-field
     * pattern as `purchase_transactions`. Akumulasi Penyusutan & Nilai
     * Buku are NEVER stored here — always derived from
     * `fixed_asset_depreciation_entries` (Task 3.10), mirroring how
     * account balances are derived from journal_lines rather than cached.
     */
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('fixed_asset_category_id')->constrained('fixed_asset_categories')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->string('ownership_document_number')->nullable();
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 18, 2);
            $table->decimal('residual_value', 18, 2);
            $table->unsignedSmallInteger('useful_life_months');
            $table->enum('depreciation_method', ['garis_lurus', 'saldo_menurun']);
            $table->enum('payment_method', ['tunai', 'kredit']);
            $table->enum('status', [
                'menunggu_approval',
                'ditolak',
                'aktif',
                'dijual',
                'dihapusbukukan',
                'nonaktif_sementara',
            ])->default('menunggu_approval');
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
        Schema::dropIfExists('fixed_assets');
    }
};
