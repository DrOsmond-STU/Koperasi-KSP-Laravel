<?php

namespace Tests\Feature\Loans;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanSchedule;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catat Angsuran (teller counter, cash) — the gap the user reported: the
 * only existing repayment path was Anggota\LoanRepaymentController (member
 * self-service through the Xendit gateway). This is a synchronous staff
 * path over the same LoanRepaymentService, no gateway involved.
 */
class StafLoanRepaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    private function petugasKredit(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_kredit');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function bendahara(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('bendahara');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    /** Three installments of Rp 1.000.000 pokok + Rp 100.000 jasa each. */
    private function disbursedLoanWithThreeInstallments(): Loan
    {
        $product = LoanProduct::factory()->create();
        $loan = Loan::factory()->create(['loan_product_id' => $product->id, 'status' => 'dicairkan', 'principal_amount' => 3000000]);

        for ($i = 1; $i <= 3; $i++) {
            LoanSchedule::query()->create([
                'loan_id' => $loan->id,
                'installment_number' => $i,
                'due_date' => now()->addMonths($i),
                'principal_amount' => 1000000,
                'interest_amount' => 100000,
                'total_amount' => 1100000,
                'paid_amount' => 0,
                'status' => 'belum_bayar',
            ]);
        }

        return $loan->fresh();
    }

    public function test_role_without_pinjaman_create_cannot_access_the_screen(): void
    {
        $user = $this->bendahara();

        $this->actingAs($user)->get(route('staf.angsuran.create'))->assertForbidden();
    }

    public function test_petugas_kredit_can_preview_the_allocation(): void
    {
        $user = $this->petugasKredit();
        $loan = $this->disbursedLoanWithThreeInstallments();

        $response = $this->actingAs($user)->post(route('staf.angsuran.preview'), [
            'loan_id' => $loan->id,
            'amount' => 1100000,
        ]);

        $response->assertOk();
        $response->assertSee('1.100.000', false);
        $response->assertSee($loan->loan_number);
    }

    public function test_petugas_kredit_can_record_a_full_installment_payment(): void
    {
        $user = $this->petugasKredit();
        $loan = $this->disbursedLoanWithThreeInstallments();

        $response = $this->actingAs($user)->post(route('staf.angsuran.store'), [
            'loan_id' => $loan->id,
            'amount' => 1100000,
        ]);

        $response->assertRedirect(route('staf.angsuran.create'));
        $this->assertDatabaseHas('loan_repayments', [
            'loan_id' => $loan->id,
            'amount' => '1100000.00',
            'principal_portion' => '1000000.00',
            'interest_portion' => '100000.00',
            'created_by' => $user->id,
        ]);
        $this->assertDatabaseHas('loan_schedules', [
            'loan_id' => $loan->id, 'installment_number' => 1, 'status' => 'lunas',
        ]);
    }

    /**
     * Regresi: form Catat Angsuran sebelumnya tidak punya field tanggal sama
     * sekali — staf yang menyusulkan angsuran lama tidak bisa mencatat
     * tanggal pembayaran yang sebenarnya, jadi semuanya tercatat "hari ini".
     */
    public function test_petugas_kredit_can_backdate_a_repayment_via_transaction_date(): void
    {
        $user = $this->petugasKredit();
        $loan = $this->disbursedLoanWithThreeInstallments();
        $paidOn = now()->subDays(5)->toDateString();

        $response = $this->actingAs($user)->post(route('staf.angsuran.store'), [
            'loan_id' => $loan->id,
            'amount' => 1100000,
            'transaction_date' => $paidOn,
        ]);

        $response->assertRedirect(route('staf.angsuran.create'));
        $this->assertDatabaseHas('loan_repayments', [
            'loan_id' => $loan->id,
            'transaction_date' => $paidOn,
        ]);
    }

    public function test_overpayment_is_rejected_with_a_friendly_error(): void
    {
        $user = $this->petugasKredit();
        $loan = $this->disbursedLoanWithThreeInstallments();

        $response = $this->actingAs($user)->post(route('staf.angsuran.store'), [
            'loan_id' => $loan->id,
            'amount' => 99999999,
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('loan_repayments', 0);
    }

    public function test_role_without_pinjaman_create_cannot_record_a_payment(): void
    {
        $user = $this->bendahara();
        $loan = $this->disbursedLoanWithThreeInstallments();

        $this->actingAs($user)->post(route('staf.angsuran.store'), [
            'loan_id' => $loan->id,
            'amount' => 1100000,
        ])->assertForbidden();
    }

    public function test_admin_pinjaman_index_links_to_print_schedule_and_catat_angsuran(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $loan = $this->disbursedLoanWithThreeInstallments();

        $response = $this->actingAs($user)->get(route('admin.pinjaman.index'));

        $response->assertOk();
        $response->assertSee(route('admin.print.loans.schedule', $loan), false);
        $response->assertSee(route('staf.angsuran.create', ['loan_id' => $loan->id]), false);
    }
}
