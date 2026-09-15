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
            'min_tenor_months' => 3,
            'max_tenor_months' => 24,
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

    /**
     * Pemetaan akun produk lama sebelumnya tidak bisa diubah dari mana pun —
     * koperasi yang mengganti bagan akunnya terjebak dengan akun kas lama
     * yang membuat pencairan gagal posting.
     */
    public function test_existing_product_journal_accounts_can_be_reassigned(): void
    {
        $user = $this->manajer();
        $accounts = ChartOfAccount::factory()->count(4)->create();

        $this->actingAs($user)->post(route('admin.master.loan-products.store'), [
            ...$this->baseFields(),
            'coa_receivable_account_id' => $accounts[0]->id,
            'coa_interest_income_account_id' => $accounts[1]->id,
            'coa_provision_income_account_id' => $accounts[2]->id,
            'coa_penalty_receivable_account_id' => $accounts[3]->id,
        ]);

        $product = LoanProduct::query()->where('code', 'PINJ-001')->firstOrFail();
        $this->assertNull($product->coa_cash_account_id);

        $unitCash = ChartOfAccount::factory()->create(['code' => '1101200', 'name' => 'KAS KECIL (USP)']);

        $response = $this->actingAs($user)->put(route('admin.master.loan-products.accounts.update', $product), [
            'coa_receivable_account_id' => $accounts[0]->id,
            'coa_interest_income_account_id' => $accounts[1]->id,
            'coa_provision_income_account_id' => $accounts[2]->id,
            'coa_penalty_receivable_account_id' => $accounts[3]->id,
            'coa_cash_account_id' => $unitCash->id,
        ]);

        $response->assertRedirect(route('admin.master.loan-products.index'));
        $this->assertEquals($unitCash->id, $product->fresh()->coa_cash_account_id);
    }

    public function test_cash_account_on_a_product_must_be_postable(): void
    {
        $user = $this->manajer();
        $accounts = ChartOfAccount::factory()->count(4)->create();
        $header = ChartOfAccount::factory()->nonPostable()->create();

        $this->actingAs($user)->post(route('admin.master.loan-products.store'), [
            ...$this->baseFields(),
            'coa_receivable_account_id' => $accounts[0]->id,
            'coa_interest_income_account_id' => $accounts[1]->id,
            'coa_provision_income_account_id' => $accounts[2]->id,
            'coa_penalty_receivable_account_id' => $accounts[3]->id,
        ]);

        $product = LoanProduct::query()->where('code', 'PINJ-001')->firstOrFail();

        $response = $this->actingAs($user)->put(route('admin.master.loan-products.accounts.update', $product), [
            'coa_receivable_account_id' => $accounts[0]->id,
            'coa_interest_income_account_id' => $accounts[1]->id,
            'coa_provision_income_account_id' => $accounts[2]->id,
            'coa_penalty_receivable_account_id' => $accounts[3]->id,
            'coa_cash_account_id' => $header->id,
        ]);

        $response->assertSessionHasErrors('coa_cash_account_id');
        $this->assertNull($product->fresh()->coa_cash_account_id);
    }
}
