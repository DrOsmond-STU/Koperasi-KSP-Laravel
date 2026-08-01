<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Saldo Awal Persediaan — kategori ke-6 migrasi saldo awal (setelah
     * Simpanan/Pinjaman/Angsuran/COA/UPF), qty+harga per barang dibawa dari
     * sistem lama. Berbeda dari UPF (murni rekonsiliasi, tidak
     * dimaterialisasi), baris ini DIMATERIALISASI ke stock_ledger saat batch
     * dikunci (OpeningBalanceLockService::materializeStock()) via
     * StockLedgerEngine::receive() — sama seperti Simpanan/Pinjaman
     * dimaterialisasi ke SavingsAccount/Loan, karena persediaan punya saldo
     * berjalan yang terus dibaca modul lain (Laporan Persediaan, POS,
     * Pembelian), bukan sekadar angka pembanding P&L seperti UPF.
     */
    public function up(): void
    {
        Schema::create('opening_balance_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_batch_id')->constrained('opening_balance_batches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('qty', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('opening_balance_batch_id', 'obs_batch_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_balance_stock');
    }
};
