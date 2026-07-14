<?php

namespace Tests\Feature\Reporting;

use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\StockLedgerEngine;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manajer_can_preview_and_queue_export(): void
    {
        Queue::fake();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        app(StockLedgerEngine::class)->receive($product, $branch->id, '10', '1000', 'pembelian', $user->id, date: Carbon::parse('2026-04-05'));

        $response = $this->actingAs($user)->post(route('admin.laporan-kustom.generate'), [
            'report_type' => 'persediaan_kartu',
            'columns' => ['transaction_date', 'qty_in'],
            'filters' => ['product_id' => $product->id, 'branch_id' => $branch->id, 'period_start' => '2026-04-01', 'period_end' => '2026-04-30'],
        ]);

        $response->assertOk();
        $response->assertSee('Preview Laporan');
    }

    public function test_column_outside_whitelist_is_rejected_at_http_layer(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        $response = $this->actingAs($user)->post(route('admin.laporan-kustom.generate'), [
            'report_type' => 'persediaan_kartu',
            'columns' => ['kolom_rahasia'],
            'filters' => ['product_id' => 1, 'period_start' => '2026-04-01', 'period_end' => '2026-04-30'],
        ]);

        $response->assertSessionHasErrors(['columns.0']);
    }

    public function test_role_without_permission_cannot_generate_report(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_upf');

        $response = $this->actingAs($user)->post(route('admin.laporan-kustom.generate'), [
            'report_type' => 'persediaan_kartu',
            'columns' => ['transaction_date'],
            'filters' => ['product_id' => 1, 'period_start' => '2026-04-01', 'period_end' => '2026-04-30'],
        ]);

        $response->assertForbidden();
    }
}
