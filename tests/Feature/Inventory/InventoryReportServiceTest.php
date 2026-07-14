<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryReportService;
use App\Services\Inventory\StockLedgerEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md STK-08: saldo awal + mutasi = saldo akhir, cocok
 * dengan Laporan Saldo Persediaan pada tanggal sama.
 */
class InventoryReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_kartu_ringkasan_matches_saldo_persediaan_on_the_same_date(): void
    {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $user = User::factory()->create();
        $engine = app(StockLedgerEngine::class);

        $engine->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id, date: Carbon::parse('2026-01-05'));
        $engine->receive($product, $branch->id, '5', '1000', 'pembelian', $user->id, date: Carbon::parse('2026-01-15'));
        $engine->issue($product, $branch->id, '3', 'penjualan', $user->id, date: Carbon::parse('2026-01-20'));

        $report = app(InventoryReportService::class);
        $ringkasan = $report->kartuRingkasan($product->id, $branch->id, '2026-01-10', '2026-01-31');

        $this->assertEquals('10.0000', $ringkasan['opening_qty']);
        $this->assertEquals('5.0000', $ringkasan['total_in']);
        $this->assertEquals('3.0000', $ringkasan['total_out']);
        $this->assertEquals('12.0000', $ringkasan['closing_qty']);

        $saldo = $report->saldoPersediaan($branch->id, '2026-01-31')->firstWhere('product.id', $product->id);
        $this->assertEquals('12.0000', $saldo['qty']);
    }

    public function test_kartu_rincian_lists_movements_chronologically(): void
    {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $user = User::factory()->create();
        $engine = app(StockLedgerEngine::class);

        $engine->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id, date: Carbon::parse('2026-01-05'));
        $engine->issue($product, $branch->id, '3', 'penjualan', $user->id, date: Carbon::parse('2026-01-10'));

        $rincian = app(InventoryReportService::class)->kartuRincian($product->id, $branch->id, '2026-01-01', '2026-01-31');

        $this->assertCount(2, $rincian);
        $this->assertEquals('pembelian', $rincian[0]->transaction_type);
        $this->assertEquals('penjualan', $rincian[1]->transaction_type);
    }

    public function test_saldo_persediaan_consolidates_across_branches_when_branch_is_null(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $product = Product::factory()->create();
        $user = User::factory()->create();
        $engine = app(StockLedgerEngine::class);

        $engine->receive($product, $branchA->id, '10', '1000', 'pembelian', $user->id);
        $engine->receive($product, $branchB->id, '4', '2000', 'pembelian', $user->id);

        $saldo = app(InventoryReportService::class)->saldoPersediaan(null)->firstWhere('product.id', $product->id);

        $this->assertEquals('14.0000', $saldo['qty']);
        $this->assertEquals('18000.00', $saldo['value']);
    }
}
