<?php

namespace Tests\Feature\Inventory;

use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockLedgerEntry;
use App\Models\User;
use App\Services\Inventory\StockLedgerEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md STK-01 (harga rata-rata berjalan), STK-02 (HPP dari
 * average cost berjalan), STK-07 (stok tidak cukup ditolak), and STK-09
 * (tidak ada balapan data pada saldo berjalan — lockForUpdate menyerialkan
 * penulisan per produk; PHPUnit berjalan single-process sehingga yang diuji
 * di sini adalah kebenaran akumulasi berurutan, bukan balapan proses nyata).
 */
class StockLedgerEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_receive_blends_weighted_average_cost(): void
    {
        $engine = app(StockLedgerEngine::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        $engine->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id);
        $entry = $engine->receive($product, $branch->id, '10', '2000', 'pembelian', $user->id);

        $this->assertEquals('20.0000', $entry->running_qty);
        $this->assertEquals('1500.0000', $entry->running_avg_cost);
    }

    public function test_issue_uses_current_average_cost_and_does_not_change_it(): void
    {
        $engine = app(StockLedgerEngine::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        $engine->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id);
        $entry = $engine->issue($product, $branch->id, '4', 'penjualan', $user->id);

        $this->assertEquals('6.0000', $entry->running_qty);
        $this->assertEquals('1000.0000', $entry->unit_cost);
        $this->assertEquals('1000.0000', $entry->running_avg_cost);
    }

    public function test_issue_more_than_available_stock_is_rejected(): void
    {
        $engine = app(StockLedgerEngine::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        $engine->receive($product, $branch->id, '5', '1000', 'pembelian', $user->id);

        $this->expectException(InsufficientStockException::class);
        $engine->issue($product, $branch->id, '10', 'penjualan', $user->id);

        $this->assertDatabaseCount('stock_ledger', 1);
    }

    public function test_balances_are_tracked_independently_per_branch(): void
    {
        $engine = app(StockLedgerEngine::class);
        $product = Product::factory()->create();
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $user = User::factory()->create();

        $engine->receive($product, $branchA->id, '10', '1000', 'pembelian', $user->id);

        $this->assertEquals('10.0000', $engine->currentBalance($product, $branchA->id)['qty']);
        $this->assertEquals('0.0000', $engine->currentBalance($product, $branchB->id)['qty']);
    }

    public function test_sequential_movements_accumulate_correctly(): void
    {
        $engine = app(StockLedgerEngine::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $engine->receive($product, $branch->id, '2', '1000', 'pembelian', $user->id);
        }

        $balance = $engine->currentBalance($product, $branch->id);
        $this->assertEquals('10.0000', $balance['qty']);
        $this->assertEquals(5, StockLedgerEntry::query()->count());
    }
}
