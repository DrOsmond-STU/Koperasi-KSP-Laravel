<?php

namespace Tests\Feature\Loans;

use App\Models\ChartOfAccount;
use App\Models\LoanProduct;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md AUTH-14 for the loan product master (Task 1.13).
 */
class LoanProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        return $user;
    }

    private function baseFields(): array
    {
        return [
            'code' => 'PINJ-001',
            'name' => 'Pinjaman Modal Usaha',
            'min_plafon' => 500000,
            'max_plafon' => 20000000,
            'min_tenor_days' => 100,
            'max_tenor_days' => 200,
            'tenor_unit' => 'hari',
            'calculation_method' => 'flat',
            'initial_rate_percentage' => 12,
        ];
    }

    public function test_creating_product_without_coa_mapping_is_rejected(): void
    {
        $user = $this->manajer();

        $response = $this->actingAs($user)->post(route('admin.master.loan-products.store'), $this->baseFields());

        $response->assertSessionHasErrors([
            'coa_receivable_account_id',
            'coa_interest_income_account_id',
            'coa_provision_income_account_id',
            'coa_penalty_receivable_account_id',
        ]);
        $this->assertDatabaseCount('loan_products', 0);
    }

    public function test_creating_product_with_non_postable_account_is_rejected(): void
    {
        $user = $this->manajer();
        $header = ChartOfAccount::factory()->nonPostable()->create();
        $ok = ChartOfAccount::factory()->count(3)->create();

        $response = $this->actingAs($user)->post(route('admin.master.loan-products.store'), [
            ...$this->baseFields(),
            'coa_receivable_account_id' => $header->id,
            'coa_interest_income_account_id' => $ok[0]->id,
            'coa_provision_income_account_id' => $ok[1]->id,
            'coa_penalty_receivable_account_id' => $ok[2]->id,
        ]);

        $response->assertSessionHasErrors(['coa_receivable_account_id']);
        $this->assertDatabaseCount('loan_products', 0);
    }

    public function test_creating_product_with_valid_accounts_succeeds(): void
    {
        $user = $this->manajer();
        $accounts = ChartOfAccount::factory()->count(4)->create();

        $response = $this->actingAs($user)->post(route('admin.master.loan-products.store'), [
            ...$this->baseFields(),
            'coa_receivable_account_id' => $accounts[0]->id,
            'coa_interest_income_account_id' => $accounts[1]->id,
            'coa_provision_income_account_id' => $accounts[2]->id,
            'coa_penalty_receivable_account_id' => $accounts[3]->id,
        ]);

        $response->assertRedirect(route('admin.master.loan-products.index'));
        $this->assertDatabaseHas('loan_products', ['code' => 'PINJ-001', 'is_active' => 1]);
    }

    /** Regresi: route admin.master.loan-products.edit/.update sempat hilang dari web.php (404) — halaman "Ubah" tidak bisa dibuka sama sekali. */
    public function test_edit_page_renders_for_existing_product(): void
    {
        $user = $this->manajer();
        $product = LoanProduct::factory()->create(['tenor_unit' => 'bulan']);

        $response = $this->actingAs($user)->get(route('admin.master.loan-products.edit', $product));

        $response->assertOk();
        $response->assertSee($product->code);
    }

    /**
     * Regresi: form Ubah Produk sebelumnya tidak punya field tenor_unit sama
     * sekali — staf tidak bisa mengubah produk lama (default 'bulan') jadi
     * harian. Ini jalur satu-satunya untuk mengubah satuan tenor produk yang
     * sudah ada (form Tambah cuma untuk produk baru).
     */
    public function test_update_can_switch_tenor_unit_from_monthly_to_daily(): void
    {
        $user = $this->manajer();
        $accounts = ChartOfAccount::factory()->count(4)->create();
        $product = LoanProduct::factory()->create([
            'tenor_unit' => 'bulan',
            'min_tenor_days' => 3,
            'max_tenor_days' => 24,
            'coa_receivable_account_id' => $accounts[0]->id,
            'coa_interest_income_account_id' => $accounts[1]->id,
            'coa_provision_income_account_id' => $accounts[2]->id,
            'coa_penalty_receivable_account_id' => $accounts[3]->id,
        ]);

        $response = $this->actingAs($user)->put(route('admin.master.loan-products.update', $product), [
            'code' => $product->code,
            'name' => $product->name,
            'min_plafon' => $product->min_plafon,
            'max_plafon' => $product->max_plafon,
            'min_tenor_days' => 100,
            'max_tenor_days' => 200,
            'tenor_unit' => 'hari',
            'calculation_method' => $product->calculation_method,
            'coa_receivable_account_id' => $accounts[0]->id,
            'coa_interest_income_account_id' => $accounts[1]->id,
            'coa_provision_income_account_id' => $accounts[2]->id,
            'coa_penalty_receivable_account_id' => $accounts[3]->id,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.master.loan-products.index'));
        $this->assertTrue($product->fresh()->usesDailyTenor());
        $this->assertEquals(100, $product->fresh()->min_tenor_days);
        $this->assertEquals(200, $product->fresh()->max_tenor_days);
    }
}
