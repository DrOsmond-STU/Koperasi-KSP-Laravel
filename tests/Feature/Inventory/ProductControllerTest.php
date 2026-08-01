<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md AUTH-14: Master Barang tidak bisa aktif tanpa
 * mapping COA Persediaan/HPP/Pendapatan Penjualan yang valid.
 */
class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        return $user;
    }

    public function test_creating_product_without_coa_mapping_is_rejected(): void
    {
        $user = $this->manajer();

        $response = $this->actingAs($user)->post(route('admin.master.products.store'), [
            'code' => 'BRG-001',
            'name' => 'Beras 5kg',
            'unit' => 'karung',
        ]);

        $response->assertSessionHasErrors([
            'coa_inventory_account_id',
            'coa_cogs_account_id',
            'coa_sales_revenue_account_id',
        ]);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_creating_product_with_non_postable_account_is_rejected(): void
    {
        $user = $this->manajer();

        $headerAccount = ChartOfAccount::factory()->nonPostable()->create();
        $postableAccount = ChartOfAccount::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.master.products.store'), [
            'code' => 'BRG-002',
            'name' => 'Beras 5kg',
            'unit' => 'karung',
            'coa_inventory_account_id' => $headerAccount->id,
            'coa_cogs_account_id' => $postableAccount->id,
            'coa_sales_revenue_account_id' => $postableAccount->id,
        ]);

        $response->assertSessionHasErrors(['coa_inventory_account_id']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_creating_product_with_valid_postable_accounts_succeeds(): void
    {
        $user = $this->manajer();

        $inventory = ChartOfAccount::factory()->create();
        $cogs = ChartOfAccount::factory()->create();
        $revenue = ChartOfAccount::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.master.products.store'), [
            'code' => 'BRG-003',
            'name' => 'Beras 5kg',
            'unit' => 'karung',
            'selling_price' => 65000,
            'coa_inventory_account_id' => $inventory->id,
            'coa_cogs_account_id' => $cogs->id,
            'coa_sales_revenue_account_id' => $revenue->id,
        ]);

        $response->assertRedirect(route('admin.master.products.index'));
        $this->assertDatabaseHas('products', ['code' => 'BRG-003', 'is_active' => 1]);
    }

    public function test_role_without_permission_cannot_create_product(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');

        $account = ChartOfAccount::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.master.products.store'), [
            'code' => 'BRG-004',
            'name' => 'Beras 5kg',
            'unit' => 'karung',
            'coa_inventory_account_id' => $account->id,
            'coa_cogs_account_id' => $account->id,
            'coa_sales_revenue_account_id' => $account->id,
        ]);

        $response->assertForbidden();
    }

    public function test_uploading_image_on_create_stores_file_and_path(): void
    {
        Storage::fake('public');
        $user = $this->manajer();
        $account = ChartOfAccount::factory()->create();
        $image = UploadedFile::fake()->image('barang.jpg');

        $this->actingAs($user)->post(route('admin.master.products.store'), [
            'code' => 'BRG-010',
            'name' => 'Barang Bergambar',
            'unit' => 'pcs',
            'coa_inventory_account_id' => $account->id,
            'coa_cogs_account_id' => $account->id,
            'coa_sales_revenue_account_id' => $account->id,
            'image' => $image,
        ]);

        $product = Product::query()->where('code', 'BRG-010')->firstOrFail();
        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_uploading_new_image_on_update_replaces_old_file(): void
    {
        Storage::fake('public');
        $user = $this->manajer();
        $account = ChartOfAccount::factory()->create();
        $oldPath = UploadedFile::fake()->image('lama.jpg')->store('products', 'public');
        $product = Product::factory()->create([
            'coa_inventory_account_id' => $account->id,
            'coa_cogs_account_id' => $account->id,
            'coa_sales_revenue_account_id' => $account->id,
            'image_path' => $oldPath,
        ]);
        $newImage = UploadedFile::fake()->image('baru.jpg');

        $this->actingAs($user)->put(route('admin.master.products.update', $product), [
            'code' => $product->code,
            'name' => $product->name,
            'unit' => $product->unit,
            'coa_inventory_account_id' => $account->id,
            'coa_cogs_account_id' => $account->id,
            'coa_sales_revenue_account_id' => $account->id,
            'image' => $newImage,
        ]);

        Storage::disk('public')->assertMissing($oldPath);
        $this->assertNotEquals($oldPath, $product->fresh()->image_path);
        Storage::disk('public')->assertExists($product->fresh()->image_path);
    }

    public function test_edit_and_update_are_gated_by_master_data_update_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');

        $account = ChartOfAccount::factory()->create();
        $product = Product::factory()->create([
            'coa_inventory_account_id' => $account->id,
            'coa_cogs_account_id' => $account->id,
            'coa_sales_revenue_account_id' => $account->id,
        ]);

        $this->actingAs($user)->put(route('admin.master.products.update', $product), [
            'code' => $product->code,
            'name' => 'Coba Ubah',
            'unit' => $product->unit,
            'coa_inventory_account_id' => $account->id,
            'coa_cogs_account_id' => $account->id,
            'coa_sales_revenue_account_id' => $account->id,
        ])->assertForbidden();
    }

    public function test_updating_product_succeeds_and_ignores_own_code(): void
    {
        $user = $this->manajer();
        $account = ChartOfAccount::factory()->create();
        $product = Product::factory()->create([
            'code' => 'BRG-011',
            'coa_inventory_account_id' => $account->id,
            'coa_cogs_account_id' => $account->id,
            'coa_sales_revenue_account_id' => $account->id,
        ]);

        $response = $this->actingAs($user)->put(route('admin.master.products.update', $product), [
            'code' => 'BRG-011',
            'name' => 'Nama Baru',
            'unit' => $product->unit,
            'selling_price' => 75000,
            'coa_inventory_account_id' => $account->id,
            'coa_cogs_account_id' => $account->id,
            'coa_sales_revenue_account_id' => $account->id,
        ]);

        $response->assertRedirect(route('admin.master.products.index'));
        $this->assertDatabaseHas('products', ['code' => 'BRG-011', 'name' => 'Nama Baru']);
    }
}
