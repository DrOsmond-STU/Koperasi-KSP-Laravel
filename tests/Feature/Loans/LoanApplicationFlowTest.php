<?php

namespace Tests\Feature\Loans;

use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md E2E-01 (pengajuan pinjaman) and E2E-04 (Manajer
 * approve dalam jenjang -> jurnal pencairan).
 */
class LoanApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole($role);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    public function test_full_application_to_disbursement_flow_below_threshold(): void
    {
        $petugasKredit = $this->userWithRole('petugas_kredit');
        $manajer = $this->userWithRole('manajer');

        $member = Member::factory()->create();
        $product = LoanProduct::factory()->create([
            'min_plafon' => 500000,
            'max_plafon' => 20000000,
            'min_tenor_days' => 3,
            'max_tenor_days' => 24,
            'approval_threshold' => 10000000,
            'calculation_method' => 'flat',
        ]);

        // Step 1: simulate (E2E-01 "simulasi pakai tarif aktif").
        $simulate = $this->actingAs($petugasKredit)->post(route('staf.pengajuan-pinjaman.simulate'), [
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'principal_amount' => 5000000,
            'tenor_days' => 12,
        ]);
        $simulate->assertOk();
        $simulate->assertSee('12'); // rate percentage shown somewhere in the table/header

        // Step 2: submit.
        $submit = $this->actingAs($petugasKredit)->post(route('staf.pengajuan-pinjaman.store'), [
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'principal_amount' => 5000000,
            'tenor_days' => 12,
        ]);
        $submit->assertRedirect(route('staf.pengajuan-pinjaman.create'));

        $this->assertDatabaseHas('loans', [
            'member_id' => $member->id,
            'principal_amount' => 5000000,
            'status' => 'diajukan',
            'required_approval_count' => 1,
        ]);

        $loan = Loan::query()->where('member_id', $member->id)->firstOrFail();

        // petugas_kredit lacks `pinjaman.approve` entirely (separate from AUTH-09
        // self-approval, which LoanApprovalServiceTest covers at the service layer).
        $selfApprove = $this->actingAs($petugasKredit)->post(route('admin.pinjaman.decide', $loan), ['decision' => 'setuju']);
        $selfApprove->assertForbidden();
        $this->assertDatabaseHas('loans', ['id' => $loan->id, 'status' => 'diajukan']);

        // Step 3 (E2E-04): Manajer approves within the required tier -> disbursement journal.
        $approve = $this->actingAs($manajer)->post(route('admin.pinjaman.decide', $loan), ['decision' => 'setuju']);
        $approve->assertRedirect(route('admin.pinjaman.index'));

        $loan->refresh();
        $this->assertEquals('dicairkan', $loan->status);
        $this->assertCount(12, $loan->schedules);

        $journalEntry = JournalEntry::query()
            ->where('source_type', Loan::class)
            ->where('source_id', $loan->id)
            ->firstOrFail();
        $this->assertEquals($journalEntry->lines->sum('debit'), $journalEntry->lines->sum('credit'));
    }

    public function test_teller_role_cannot_open_approval_queue(): void
    {
        $teller = $this->userWithRole('teller');

        $this->actingAs($teller)
            ->get(route('admin.pinjaman.index'))
            ->assertForbidden();
    }
}
