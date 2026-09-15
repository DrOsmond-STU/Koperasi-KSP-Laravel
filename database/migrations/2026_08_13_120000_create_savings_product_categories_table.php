<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori Produk Simpanan dijadikan data master.
 *
 * Sebelumnya `savings_products.category` adalah enum 4 nilai yang terkunci di
 * kode, sehingga koperasi tidak bisa menambah kategori sendiri — kasus nyata:
 * produk "Retribusi Pasar" (582 rekening di sistem lama) tidak punya padanan
 * di antara pokok/wajib/sukarela/berjangka.
 *
 * Aman dilakukan karena nilai kategori TIDAK dipakai logika bisnis mana pun
 * (bukan di ShuService, akuntansi, maupun laporan) — hanya label deskriptif.
 * Kolomnya diubah jadi VARCHAR dan divalidasi terhadap tabel master ini,
 * bukan diganti foreign key, supaya baris produk yang sudah ada tetap valid
 * apa adanya tanpa migrasi data.
 */
return new class extends Migration
{
    /** Empat nilai enum lama — tetap ada supaya data lama tidak yatim. */
    private const BAWAAN = [
        ['code' => 'pokok', 'name' => 'Simpanan Pokok'],
        ['code' => 'wajib', 'name' => 'Simpanan Wajib'],
        ['code' => 'sukarela', 'name' => 'Simpanan Sukarela'],
        ['code' => 'berjangka', 'name' => 'Simpanan Berjangka'],
    ];

    public function up(): void
    {
        Schema::create('savings_product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('savings_product_categories')->insert(
            collect(self::BAWAAN)
                ->map(fn (array $row) => [...$row, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now])
                ->all()
        );

        Schema::table('savings_products', function (Blueprint $table) {
            $table->string('category', 30)->change();
        });
    }

    public function down(): void
    {
        // Kategori tambahan buatan pengguna dikembalikan ke 'sukarela' supaya
        // muat lagi ke dalam enum lama; tanpa ini ALTER akan gagal.
        DB::table('savings_products')
            ->whereNotIn('category', array_column(self::BAWAAN, 'code'))
            ->update(['category' => 'sukarela']);

        Schema::table('savings_products', function (Blueprint $table) {
            $table->enum('category', array_column(self::BAWAAN, 'code'))->change();
        });

        Schema::dropIfExists('savings_product_categories');
    }
};
