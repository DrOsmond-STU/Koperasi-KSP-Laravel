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
            'min_tenor_days' => 3,
            'max_tenor_days' => 24,
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

    private function createProduct(): LoanProduct
    {
        $accounts = ChartOfAccount::factory()->count(4)->create();

        return LoanProduct::factory()->create([
            'coa_receivable_account_id' => $accounts[0]->id,
            'coa_interest_income_account_id' => $accounts[1]->id,
            'coa_provision_income_account_id' => $accounts[2]->id,
            'coa_penalty_receivable_account_id' => $accounts[3]->id,
        ]);
    }

    public function test_editing_product_saves_new_metadata_without_touching_rate_history(): void
    {
        $user = $this->manajer();
        $product = $this->createProduct();
        $originalRateHistoryCount = $product->rateHistory()->count();

        $newAccounts = ChartOfAccount::factory()->count(4)->create();

        $response = $this->actingAs($user)->put(route('admin.master.loan-products.update', $product), [
            'code' => $product->code,
            'name' => 'Pinjaman Modal Baru',
            'min_plafon' => 1000000,
            'max_plafon' => 30000000,
            'min_tenor_days' => 7,
            'max_tenor_days' => 90,
            'calculation_method' => 'anuitas',
            'provision_fee_percentage' => 1.5,
            'penalty_percentage_per_day' => 0.2,
            'approval_threshold' => 15000000,
            'coa_receivable_account_id' => $newAccounts[0]->id,
            'coa_interest_income_account_id' => $newAccounts[1]->id,
            'coa_provision_income_account_id' => $newAccounts[2]->id,
            'coa_penalty_receivable_account_id' => $newAccounts[3]->id,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.master.loan-products.index'));
        $product->refresh();
        $this->assertSame('Pinjaman Modal Baru', $product->name);
        $this->assertEquals(30000000, $product->max_plafon);
        $this->assertEquals(90, $product->max_tenor_days);
        $this->assertSame('anuitas', $product->calculation_method);
        $this->assertEquals($newAccounts[0]->id, $product->coa_receivable_account_id);
        // Rate history TIDAK boleh disentuh oleh update produk.
        $this->assertSame($originalRateHistoryCount, $product->rateHistory()->count());
    }

    public function test_editing_product_can_toggle_active_status(): void
    {
        $user = $this->manajer();
        $product = $this->createProduct();
        $this->assertTrue($product->is_active);

        // Kirim payload tanpa `is_active` — checkbox tidak dicentang.
        $response = $this->actingAs($user)->put(route('admin.master.loan-products.update', $product), [
            'code' => $product->code,
            'name' => $product->name,
            'min_plafon' => $product->min_plafon,
            'max_plafon' => $product->max_plafon,
            'min_tenor_days' => $product->min_tenor_days,
            'max_tenor_days' => $product->max_tenor_days,
            'calculation_method' => $product->calculation_method,
            'coa_receivable_account_id' => $product->coa_receivable_account_id,
            'coa_interest_income_account_id' => $product->coa_interest_income_account_id,
            'coa_provision_income_account_id' => $product->coa_provision_income_account_id,
            'coa_penalty_receivable_account_id' => $product->coa_penalty_receivable_account_id,
        ]);

        $response->assertRedirect(route('admin.master.loan-products.index'));
        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_editing_product_rejects_duplicate_code_of_another_product(): void
    {
        $user = $this->manajer();
        $productA = $this->createProduct();
        $productB = $this->createProduct(); // ambil kode berbeda dari factory sequence

        $this->assertNotSame($productA->code, $productB->code);

        $response = $this->actingAs($user)->put(route('admin.master.loan-products.update', $productB), [
            'code' => $productA->code, // tabrakan
            'name' => $productB->name,
            'min_plafon' => $productB->min_plafon,
            'max_plafon' => $productB->max_plafon,
            'min_tenor_days' => $productB->min_tenor_days,
            'max_tenor_days' => $productB->max_tenor_days,
            'calculation_method' => $productB->calculation_method,
            'coa_receivable_account_id' => $productB->coa_receivable_account_id,
            'coa_interest_income_account_id' => $productB->coa_interest_income_account_id,
            'coa_provision_income_account_id' => $productB->coa_provision_income_account_id,
            'coa_penalty_receivable_account_id' => $productB->coa_penalty_receivable_account_id,
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_editing_product_allows_keeping_same_code(): void
    {
        $user = $this->manajer();
        $product = $this->createProduct();

        $response = $this->actingAs($user)->put(route('admin.master.loan-products.update', $product), [
            'code' => $product->code, // sama, tidak boleh ditolak unique
            'name' => 'Nama Baru',
            'min_plafon' => $product->min_plafon,
            'max_plafon' => $product->max_plafon,
            'min_tenor_days' => $product->min_tenor_days,
            'max_tenor_days' => $product->max_tenor_days,
            'calculation_method' => $product->calculation_method,
            'coa_receivable_account_id' => $product->coa_receivable_account_id,
            'coa_interest_income_account_id' => $product->coa_interest_income_account_id,
            'coa_provision_income_account_id' => $product->coa_provision_income_account_id,
            'coa_penalty_receivable_account_id' => $product->coa_penalty_receivable_account_id,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.master.loan-products.index'));
        $this->assertSame('Nama Baru', $product->fresh()->name);
    }
}
