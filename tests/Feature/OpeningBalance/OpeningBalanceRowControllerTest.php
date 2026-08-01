<?php

namespace Tests\Feature\OpeningBalance;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\OpeningBalanceBatch;
use App\Models\RetributionType;
use App\Models\SavingsProduct;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manual per-row entry (all 5 sub-modules), row delete, draft/locked guards,
 * template download, and UPF reconciliation — covers the gap closed by
 * "input manual satu-per-satu + kategori UPF baru".
 */
class OpeningBalanceRowControllerTest extends TestCase
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

    private function batch(string $status = 'draft'): OpeningBalanceBatch
    {
        return OpeningBalanceBatch::query()->create([
            'branch_id' => Branch::factory()->create()->id,
            'cutoff_date' => '2026-01-01',
            'status' => $status,
        ]);
    }

    public function test_manual_entry_succeeds_for_all_five_categories(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();
        $member = Member::factory()->create();
        $savingsProduct = SavingsProduct::factory()->create();
        $loanProduct = LoanProduct::factory()->create();
        $account = ChartOfAccount::factory()->create();
        $revenueAccount = ChartOfAccount::factory()->create();
        $retributionType = RetributionType::factory()->create([
            'coa_revenue_account_id' => $revenueAccount->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('admin.saldo-awal.row.savings.store', $batch), [
            'member_id' => $member->id,
            'savings_product_id' => $savingsProduct->id,
            'balance' => 1000000,
        ])->assertRedirect(route('admin.saldo-awal.show', $batch));
        $this->assertDatabaseCount('opening_balance_savings', 1);

        $this->actingAs($user)->post(route('admin.saldo-awal.row.loans.store', $batch), [
            'member_id' => $member->id,
            'loan_product_id' => $loanProduct->id,
            'disbursement_date' => '2025-06-01',
            'original_principal' => 5000000,
            'outstanding_principal' => 3000000,
            'tenor_months' => 12,
            'remaining_tenor_months' => 6,
            'next_installment_number' => 7,
            'next_due_date' => '2026-02-01',
            'collectibility' => 'lancar',
        ])->assertRedirect(route('admin.saldo-awal.show', $batch));
        $loanId = $batch->loans()->firstOrFail()->id;
        $this->assertDatabaseCount('opening_balance_loans', 1);

        $this->actingAs($user)->post(route('admin.saldo-awal.row.installments.store', $batch), [
            'opening_balance_loan_id' => $loanId,
            'installment_number' => 7,
            'due_date' => '2026-02-01',
            'principal_amount' => 400000,
            'interest_amount' => 50000,
            'status' => 'belum_bayar',
        ])->assertRedirect(route('admin.saldo-awal.show', $batch));
        $this->assertDatabaseCount('opening_balance_installments', 1);

        $this->actingAs($user)->post(route('admin.saldo-awal.row.coa.store', $batch), [
            'chart_of_account_id' => $account->id,
            'position' => 'debit',
            'amount' => 1000000,
        ])->assertRedirect(route('admin.saldo-awal.show', $batch));
        $this->assertDatabaseCount('opening_balance_coa', 1);

        $this->actingAs($user)->post(route('admin.saldo-awal.row.upf.store', $batch), [
            'retribution_type_id' => $retributionType->id,
            'amount' => 250000,
            'notes' => 'Migrasi',
        ])->assertRedirect(route('admin.saldo-awal.show', $batch));
        $this->assertDatabaseCount('opening_balance_upf', 1);
    }

    public function test_manual_entry_rejects_invalid_foreign_key(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();

        $response = $this->actingAs($user)->post(route('admin.saldo-awal.row.savings.store', $batch), [
            'member_id' => 999999,
            'savings_product_id' => 999999,
            'balance' => 1000000,
        ]);

        $response->assertSessionHasErrors(['member_id', 'savings_product_id']);
        $this->assertDatabaseCount('opening_balance_savings', 0);
    }

    public function test_delete_row_succeeds_while_draft(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();
        $row = $batch->savings()->create([
            'member_id' => Member::factory()->create()->id,
            'savings_product_id' => SavingsProduct::factory()->create()->id,
            'balance' => 500000,
        ]);

        $response = $this->actingAs($user)->delete(route('admin.saldo-awal.row.destroy', [$batch, 'savings', $row->id]));

        $response->assertRedirect(route('admin.saldo-awal.show', $batch));
        $this->assertDatabaseCount('opening_balance_savings', 0);
    }

    public function test_manual_entry_rejected_when_batch_locked(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch('locked');

        $response = $this->actingAs($user)->post(route('admin.saldo-awal.row.savings.store', $batch), [
            'member_id' => Member::factory()->create()->id,
            'savings_product_id' => SavingsProduct::factory()->create()->id,
            'balance' => 1000000,
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('opening_balance_savings', 0);
    }

    public function test_delete_row_rejected_when_batch_locked(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();
        $row = $batch->savings()->create([
            'member_id' => Member::factory()->create()->id,
            'savings_product_id' => SavingsProduct::factory()->create()->id,
            'balance' => 500000,
        ]);
        $batch->update(['status' => 'locked']);

        $response = $this->actingAs($user)->delete(route('admin.saldo-awal.row.destroy', [$batch, 'savings', $row->id]));

        $response->assertNotFound();
        $this->assertDatabaseCount('opening_balance_savings', 1);
    }

    public function test_teller_cannot_manually_add_row(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);
        $batch = $this->batch();

        $response = $this->actingAs($user)->post(route('admin.saldo-awal.row.savings.store', $batch), [
            'member_id' => Member::factory()->create()->id,
            'savings_product_id' => SavingsProduct::factory()->create()->id,
            'balance' => 1000000,
        ]);

        $response->assertForbidden();
    }

    public function test_download_template_returns_csv_with_expected_headers(): void
    {
        $user = $this->bendahara();

        $response = $this->actingAs($user)->get(route('admin.saldo-awal.template', 'upf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('kode_jenis_retribusi,saldo_awal,keterangan', $response->streamedContent());
    }

    public function test_download_template_rejects_unknown_sub_module(): void
    {
        $user = $this->bendahara();

        $this->actingAs($user)->get(route('admin.saldo-awal.template', 'tidak-ada'))
            ->assertNotFound();
    }

    /** UPF discrepancy is detected when sub-module total doesn't match the matching COA credit line. */
    public function test_upf_discrepancy_detected_when_amount_does_not_match_coa(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();
        $revenueAccount = ChartOfAccount::factory()->create();
        $retributionType = RetributionType::factory()->create([
            'coa_revenue_account_id' => $revenueAccount->id,
            'is_active' => true,
        ]);

        $batch->upf()->create(['retribution_type_id' => $retributionType->id, 'amount' => 250000]);
        $batch->coaLines()->create(['chart_of_account_id' => $revenueAccount->id, 'position' => 'kredit', 'amount' => 100000]);

        $response = $this->actingAs($user)->get(route('admin.saldo-awal.show', $batch));

        $response->assertOk();
        $response->assertViewHas('report', function ($report) {
            return count($report->upfDiscrepancies) === 1 && ! $report->isFullyBalanced();
        });
    }

    public function test_upf_reconciles_when_amount_matches_coa(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();
        $revenueAccount = ChartOfAccount::factory()->create();
        $retributionType = RetributionType::factory()->create([
            'coa_revenue_account_id' => $revenueAccount->id,
            'is_active' => true,
        ]);

        $batch->upf()->create(['retribution_type_id' => $retributionType->id, 'amount' => 250000]);
        $batch->coaLines()->create(['chart_of_account_id' => $revenueAccount->id, 'position' => 'kredit', 'amount' => 250000]);

        $response = $this->actingAs($user)->get(route('admin.saldo-awal.show', $batch));

        $response->assertViewHas('report', function ($report) {
            return count($report->upfDiscrepancies) === 0;
        });
    }

    public function test_show_page_renders_manual_entry_forms_and_row_tables(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();
        $row = $batch->savings()->create([
            'member_id' => Member::factory()->create(['name' => 'Anggota Uji'])->id,
            'savings_product_id' => SavingsProduct::factory()->create()->id,
            'balance' => 750000,
        ]);

        $response = $this->actingAs($user)->get(route('admin.saldo-awal.show', $batch));

        $response->assertOk();
        $response->assertSee('Anggota Uji');
        $response->assertSee('Rp 750.000');
        $response->assertSee(route('admin.saldo-awal.row.savings.store', $batch), false);
        $response->assertSee(route('admin.saldo-awal.row.destroy', [$batch, 'savings', $row->id]), false);
        $response->assertSee(route('admin.saldo-awal.template', 'savings'), false);
    }
}
