<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master Barang (dinamis, PRD §13.1). Stok & harga rata-rata berjalan
     * TIDAK disimpan di sini — keduanya dihitung dari `stock_ledger` (satu-
     * satunya sumber kebenaran, sama seperti saldo akun COA dihitung dari
     * journal_lines, bukan disimpan sebagai kolom). `coa_*` wajib diisi
     * (AUTH-14) sebelum barang bisa `is_active`.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('unit', 30);
            $table->decimal('purchase_price', 18, 2)->default(0);
            $table->decimal('selling_price', 18, 2)->default(0);
            $table->foreignId('coa_inventory_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('coa_cogs_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('coa_sales_revenue_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
