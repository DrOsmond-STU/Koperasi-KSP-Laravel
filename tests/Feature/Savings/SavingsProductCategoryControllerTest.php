<?php

namespace Tests\Feature\Savings;

use App\Models\ChartOfAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsProductCategory;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master Kategori Produk Simpanan.
 *
 * Sebelumnya kategori adalah enum terkunci (pokok/wajib/sukarela/berjangka)
 * sehingga koperasi tidak bisa menambah kategori sendiri — produk lama
 * "Retribusi Pasar" tidak punya padanan. Sekarang jadi data master ber-CRUD.
 */
class SavingsProductCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    /** Migrasi membawa empat kategori bawaan dari enum lama. */
    public function test_empat_kategori_bawaan_tersedia_setelah_migrasi(): void
    {
        $codes = SavingsProductCategory::query()->orderBy('code')->pluck('code')->all();

        $this->assertSame(['berjangka', 'pokok', 'sukarela', 'wajib'], $codes);
    }

    public function test_daftar_kategori_bisa_dibuka(): void
    {
        $response = $this->actingAs($this->manajer())
            ->get(route('admin.master.savings-product-categories.index'));

        $response->assertOk();
        $response->assertSee('Simpanan Pokok');
        $response->assertSee('Kategori Produk Simpanan');
    }

    public function test_kategori_baru_bisa_ditambahkan(): void
    {
        $response = $this->actingAs($this->manajer())
            ->post(route('admin.master.savings-product-categories.store'), [
                'code' => 'retribusi',
                'name' => 'Retribusi Pasar',
                'description' => 'Setoran retribusi pedagang pasar (unit UPF).',
            ]);

        $response->assertRedirect(route('admin.master.savings-product-categories.index'));
        $this->assertDatabaseHas('savings_product_categories', [
            'code' => 'retribusi',
            'name' => 'Retribusi Pasar',
            'is_active' => true,
        ]);
    }

    public function test_kode_dinormalkan_ke_huruf_kecil(): void
    {
        $this->actingAs($this->manajer())
            ->post(route('admin.master.savings-product-categories.store'), [
                'code' => '  Retribusi  ',
                'name' => 'Retribusi Pasar',
            ]);

        $this->assertDatabaseHas('savings_product_categories', ['code' => 'retribusi']);
    }

    public function test_kode_duplikat_dan_format_salah_ditolak(): void
    {
        $user = $this->manajer();

        $this->actingAs($user)
            ->post(route('admin.master.savings-product-categories.store'), ['code' => 'pokok', 'name' => 'Duplikat'])
            ->assertSessionHasErrors('code');

        $this->actingAs($user)
            ->post(route('admin.master.savings-product-categories.store'), ['code' => 'ada spasi!', 'name' => 'Salah Format'])
            ->assertSessionHasErrors('code');
    }

    public function test_kategori_bisa_diubah_dan_dinonaktifkan(): void
    {
        $category = SavingsProductCategory::query()->where('code', 'berjangka')->firstOrFail();

        $response = $this->actingAs($this->manajer())
            ->put(route('admin.master.savings-product-categories.update', $category), [
                'name' => 'Simpanan Berjangka (Deposito)',
                'description' => 'Jangka waktu tetap.',
            ]);

        $response->assertRedirect(route('admin.master.savings-product-categories.index'));
        $this->assertDatabaseHas('savings_product_categories', [
            'id' => $category->id,
            'name' => 'Simpanan Berjangka (Deposito)',
            'is_active' => false, // checkbox tidak dikirim = nonaktif
        ]);
    }

    public function test_kategori_yang_masih_dipakai_tidak_bisa_dihapus(): void
    {
        $category = SavingsProductCategory::query()->where('code', 'sukarela')->firstOrFail();
        SavingsProduct::factory()->create(['category' => 'sukarela']);

        $response = $this->actingAs($this->manajer())
            ->delete(route('admin.master.savings-product-categories.destroy', $category));

        $response->assertRedirect(route('admin.master.savings-product-categories.index'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('masih dipakai', session('error'));
        $this->assertDatabaseHas('savings_product_categories', ['id' => $category->id]);
    }

    public function test_kategori_yang_tidak_dipakai_bisa_dihapus(): void
    {
        $category = SavingsProductCategory::query()->where('code', 'berjangka')->firstOrFail();

        $response = $this->actingAs($this->manajer())
            ->delete(route('admin.master.savings-product-categories.destroy', $category));

        $response->assertRedirect(route('admin.master.savings-product-categories.index'));
        $this->assertDatabaseMissing('savings_product_categories', ['id' => $category->id]);
    }

    /** Inti dari perubahan ini: produk bisa memakai kategori buatan sendiri. */
    public function test_produk_simpanan_bisa_dibuat_dengan_kategori_baru(): void
    {
        $user = $this->manajer();

        SavingsProductCategory::query()->create([
            'code' => 'retribusi',
            'name' => 'Retribusi Pasar',
            'is_active' => true,
        ]);

        $liability = ChartOfAccount::factory()->create(['is_postable' => true]);
        $expense = ChartOfAccount::factory()->create(['is_postable' => true]);

        $response = $this->actingAs($user)->post(route('admin.master.savings-products.store'), [
            'code' => '12',
            'name' => 'Retribusi Pasar',
            'category' => 'retribusi',
            'interest_method' => 'flat',
            'coa_liability_account_id' => $liability->id,
            'coa_interest_expense_account_id' => $expense->id,
            'initial_rate_percentage' => 0,
        ]);

        $response->assertRedirect(route('admin.master.savings-products.index'));
        $this->assertDatabaseHas('savings_products', ['code' => '12', 'category' => 'retribusi']);
    }

    public function test_kategori_nonaktif_ditolak_saat_membuat_produk(): void
    {
        $user = $this->manajer();

        SavingsProductCategory::query()->create([
            'code' => 'arsip',
            'name' => 'Kategori Arsip',
            'is_active' => false,
        ]);

        $liability = ChartOfAccount::factory()->create(['is_postable' => true]);
        $expense = ChartOfAccount::factory()->create(['is_postable' => true]);

        $this->actingAs($user)->post(route('admin.master.savings-products.store'), [
            'code' => 'X1',
            'name' => 'Produk Arsip',
            'category' => 'arsip',
            'interest_method' => 'flat',
            'coa_liability_account_id' => $liability->id,
            'coa_interest_expense_account_id' => $expense->id,
            'initial_rate_percentage' => 0,
        ])->assertSessionHasErrors('category');
    }
}
