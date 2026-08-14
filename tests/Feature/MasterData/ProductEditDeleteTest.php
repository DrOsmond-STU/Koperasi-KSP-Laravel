<?php

namespace Tests\Feature\MasterData;

use App\Models\ChartOfAccount;
use App\Models\LoanProduct;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsProductCategory;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ubah & hapus Produk Simpanan / Produk Pinjaman.
 *
 * Kedua master sebelumnya hanya punya index/create/store, sehingga pemetaan
 * akun COA yang salah tidak bisa diperbaiki sama sekali dari aplikasi — itu
 * yang diperbaiki di sini.
 *
 * Sifat yang dijaga: produk yang sudah dipakai rekening/pinjaman atau masih
 * dirujuk Saldo Awal TIDAK boleh terhapus, dan alasannya harus terbaca.
 */
class ProductEditDeleteTest extends TestCase
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

    private function akun(string $code): ChartOfAccount
    {
        return ChartOfAccount::query()->create([
            'code' => $code, 'name' => 'Akun '.$code, 'type' => 'ASET',
            'normal_balance' => 'DEBIT', 'is_postable' => true, 'statement' => 'NERACA',
        ]);
    }

    private function kategori(): SavingsProductCategory
    {
        return SavingsProductCategory::query()->firstOrCreate(
            ['code' => 'sukarela'],
            ['name' => 'Sukarela', 'is_active' => true],
        );
    }

    private function produkSimpanan(): SavingsProduct
    {
        return SavingsProduct::factory()->create([
            'category' => $this->kategori()->code,
            'coa_liability_account_id' => $this->akun('9001')->id,
            'coa_interest_expense_account_id' => $this->akun('9002')->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dataSimpanan(SavingsProduct $product, array $ganti = []): array
    {
        return [
            'code' => $product->code,
            'name' => $product->name,
            'category' => $product->category,
            'interest_method' => 'saldo_harian',
            'coa_liability_account_id' => $product->coa_liability_account_id,
            'coa_interest_expense_account_id' => $product->coa_interest_expense_account_id,
            ...$ganti,
        ];
    }

    public function test_pemetaan_coa_produk_simpanan_bisa_diperbaiki(): void
    {
        $product = $this->produkSimpanan();
        $akunBaru = $this->akun('9100');

        $this->actingAs($this->manajer())
            ->put(route('admin.master.savings-products.update', $product),
                $this->dataSimpanan($product, ['coa_liability_account_id' => $akunBaru->id]))
            ->assertRedirect(route('admin.master.savings-products.index'));

        $this->assertSame($akunBaru->id, $product->fresh()->coa_liability_account_id);
    }

    /** Menyimpan ulang tanpa mengubah kode tidak boleh ditolak sebagai duplikat. */
    public function test_kode_sendiri_tidak_dianggap_duplikat(): void
    {
        $product = $this->produkSimpanan();

        $this->actingAs($this->manajer())
            ->put(route('admin.master.savings-products.update', $product), $this->dataSimpanan($product, ['name' => 'Nama Baru']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Nama Baru', $product->fresh()->name);
    }

    public function test_akun_coa_non_postable_ditolak(): void
    {
        $product = $this->produkSimpanan();
        $header = ChartOfAccount::query()->create([
            'code' => '9200', 'name' => 'Header', 'type' => 'ASET',
            'normal_balance' => 'DEBIT', 'is_postable' => false, 'statement' => 'NERACA',
        ]);

        $this->actingAs($this->manajer())
            ->put(route('admin.master.savings-products.update', $product),
                $this->dataSimpanan($product, ['coa_liability_account_id' => $header->id]))
            ->assertSessionHasErrors('coa_liability_account_id');
    }

    public function test_produk_simpanan_tanpa_rekening_bisa_dihapus(): void
    {
        $product = $this->produkSimpanan();

        $this->actingAs($this->manajer())
            ->delete(route('admin.master.savings-products.destroy', $product))
            ->assertRedirect(route('admin.master.savings-products.index'));

        $this->assertDatabaseMissing('savings_products', ['id' => $product->id]);
    }

    /** Produk yang sudah punya rekening anggota harus ditolak, bukan mengorbankan riwayat. */
    public function test_produk_simpanan_berrekening_tidak_bisa_dihapus(): void
    {
        $product = $this->produkSimpanan();
        SavingsAccount::factory()->create(['savings_product_id' => $product->id]);

        $this->actingAs($this->manajer())
            ->delete(route('admin.master.savings-products.destroy', $product))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('savings_products', ['id' => $product->id]);
    }

    public function test_nonaktifkan_produk_simpanan(): void
    {
        $product = $this->produkSimpanan();
        $this->assertTrue($product->is_active);

        $this->actingAs($this->manajer())
            ->post(route('admin.master.savings-products.toggle-active', $product));

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_pemetaan_coa_produk_pinjaman_bisa_diperbaiki(): void
    {
        $product = LoanProduct::factory()->create([
            'coa_receivable_account_id' => $this->akun('8001')->id,
            'coa_interest_income_account_id' => $this->akun('8002')->id,
            'coa_provision_income_account_id' => $this->akun('8003')->id,
            'coa_penalty_receivable_account_id' => $this->akun('8004')->id,
        ]);
        $akunBaru = $this->akun('8100');

        $this->actingAs($this->manajer())
            ->put(route('admin.master.loan-products.update', $product), [
                'code' => $product->code,
                'name' => $product->name,
                'min_plafon' => $product->min_plafon,
                'max_plafon' => $product->max_plafon,
                'min_tenor_months' => $product->min_tenor_months,
                'max_tenor_months' => $product->max_tenor_months,
                'calculation_method' => 'efektif',
                'coa_receivable_account_id' => $akunBaru->id,
                'coa_interest_income_account_id' => $product->coa_interest_income_account_id,
                'coa_provision_income_account_id' => $product->coa_provision_income_account_id,
                'coa_penalty_receivable_account_id' => $product->coa_penalty_receivable_account_id,
            ])
            ->assertRedirect(route('admin.master.loan-products.index'));

        $this->assertSame($akunBaru->id, $product->fresh()->coa_receivable_account_id);
    }

    public function test_layar_ubah_butuh_wewenang(): void
    {
        $product = $this->produkSimpanan();
        $this->seed(RolePermissionSeeder::class);
        $anggota = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $anggota->assignRole('anggota');

        $this->actingAs($anggota)
            ->get(route('admin.master.savings-products.edit', $product))
            ->assertForbidden();

        $this->actingAs($this->manajer())
            ->get(route('admin.master.savings-products.edit', $product))
            ->assertOk();
    }
}
