<?php

namespace Tests\Feature\OpeningBalance;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\OpeningBalanceBatch;
use App\Models\Product;
use App\Models\StockLedgerEntry;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saldo Awal Persediaan — kategori ke-6 migrasi saldo awal. Berbeda dari UPF
 * (murni rekonsiliasi), baris ini dimaterialisasi ke stock_ledger saat batch
 * dikunci (OpeningBalanceLockService::materializeStock()).
 */
class OpeningBalanceStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function bendahara(): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('bendahara');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function batch(): OpeningBalanceBatch
    {
        return OpeningBalanceBatch::query()->create([
            'branch_id' => Branch::factory()->create()->id,
            'cutoff_date' => '2026-01-01',
            'status' => 'draft',
        ]);
    }

    public function test_manual_entry_succeeds_for_stock_category(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();
        $product = Product::factory()->create();

        $this->actingAs($user)->post(route('admin.saldo-awal.row.stock.store', $batch), [
            'product_id' => $product->id,
            'qty' => 50,
            'unit_cost' => 12000,
            'notes' => 'Migrasi',
        ])->assertRedirect(route('admin.saldo-awal.show', $batch));

        $this->assertDatabaseCount('opening_balance_stock', 1);
    }

    public function test_manual_entry_rejects_invalid_product(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();

        $response = $this->actingAs($user)->post(route('admin.saldo-awal.row.stock.store', $batch), [
            'product_id' => 999999,
            'qty' => 50,
            'unit_cost' => 12000,
        ]);

        $response->assertSessionHasErrors(['product_id']);
        $this->assertDatabaseCount('opening_balance_stock', 0);
    }

    public function test_stock_discrepancy_detected_when_value_does_not_match_coa(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();
        $inventoryAccount = ChartOfAccount::factory()->create();
        $product = Product::factory()->create(['coa_inventory_account_id' => $inventoryAccount->id]);

        $batch->stock()->create(['product_id' => $product->id, 'qty' => 50, 'unit_cost' => 12000]);
        $batch->coaLines()->create(['chart_of_account_id' => $inventoryAccount->id, 'position' => 'debit', 'amount' => 100000]);

        $response = $this->actingAs($user)->get(route('admin.saldo-awal.show', $batch));

        $response->assertOk();
        $response->assertViewHas('report', function ($report) {
            return count($report->stockDiscrepancies) === 1 && ! $report->isFullyBalanced();
        });
    }

    public function test_lock_materializes_stock_ledger_entry_with_correct_running_balance(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();
        $inventoryAccount = ChartOfAccount::factory()->create();
        $equityAccount = ChartOfAccount::factory()->create();
        $product = Product::factory()->create(['coa_inventory_account_id' => $inventoryAccount->id]);

        $batch->stock()->create(['product_id' => $product->id, 'qty' => 50, 'unit_cost' => 12000]);
        $batch->coaLines()->create(['chart_of_account_id' => $inventoryAccount->id, 'position' => 'debit', 'amount' => 600000]);
        $batch->coaLines()->create(['chart_of_account_id' => $equityAccount->id, 'position' => 'kredit', 'amount' => 600000]);

        $response = $this->actingAs($user)->post(route('admin.saldo-awal.lock', $batch));

        $response->assertSessionHas('status');
        $this->assertEquals('locked', $batch->fresh()->status);

        $entry = StockLedgerEntry::query()
            ->where('product_id', $product->id)
            ->where('branch_id', $batch->branch_id)
            ->where('transaction_type', 'saldo_awal')
            ->firstOrFail();

        $this->assertEquals(50, (float) $entry->qty_in);
        $this->assertEquals(50, (float) $entry->running_qty);
        $this->assertEquals(12000, (float) $entry->running_avg_cost);
    }

    public function test_download_template_returns_csv_with_expected_headers(): void
    {
        $user = $this->bendahara();

        $response = $this->actingAs($user)->get(route('admin.saldo-awal.template', 'stock'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('kode_barang,qty,harga_satuan,keterangan', $response->streamedContent());
    }
}
