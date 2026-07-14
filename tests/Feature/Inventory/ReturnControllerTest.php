<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md STK-03: retur pembelian/penjualan tanpa referensi
 * transaksi asal ditolak — ditegakkan lewat validasi Form Request
 * (purchase_item_id / pos_sale_item_id wajib & harus valid).
 */
class ReturnControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_return_without_original_item_reference_is_rejected(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $response = $this->actingAs($user)->post(route('admin.pembelian.retur.store'), [
            'qty' => 1,
        ]);

        $response->assertSessionHasErrors(['purchase_item_id', 'stock_reason_id']);
        $this->assertDatabaseCount('purchase_returns', 0);
    }

    public function test_sales_return_without_original_item_reference_is_rejected(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $response = $this->actingAs($user)->post(route('staf.pos.retur.store'), [
            'qty' => 1,
        ]);

        $response->assertSessionHasErrors(['pos_sale_item_id']);
        $this->assertDatabaseCount('sales_returns', 0);
    }
}
