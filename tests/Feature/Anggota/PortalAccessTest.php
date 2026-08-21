<?php

namespace Tests\Feature\Anggota;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanSchedule;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Portal Anggota — every controller resolves "whose data" from
 * $request->user()->member, never from request input. These tests exist
 * specifically to prove one anggota can never reach another anggota's
 * savings/loan data by guessing an ID in the URL.
 */
class PortalAccessTest extends TestCase
{
    use RefreshDatabase;

    private function anggotaWithMember(): array
    {
        $this->seed(RolePermissionSeeder::class);
        $member = Member::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('anggota');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);
        $member->update(['user_id' => $user->id]);

        return [$user->fresh(), $member->fresh()];
    }

    public function test_user_without_linked_member_is_blocked_from_portal(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('anggota');

        $this->actingAs($user)->get(route('portal.dashboard'))->assertForbidden();
    }

    public function test_member_can_view_own_dashboard(): void
    {
        [$user, $member] = $this->anggotaWithMember();
        SavingsAccount::factory()->create(['member_id' => $member->id, 'balance' => 250000]);

        $response = $this->actingAs($user)->get(route('portal.dashboard'));

        $response->assertOk();
        $response->assertSee('Rp 250.000');
    }

    public function test_member_sees_only_own_savings_accounts(): void
    {
        [$userA, $memberA] = $this->anggotaWithMember();
        [, $memberB] = $this->anggotaWithMember();

        $accountA = SavingsAccount::factory()->create(['member_id' => $memberA->id]);
        SavingsAccount::factory()->create(['member_id' => $memberB->id]);

        $response = $this->actingAs($userA)->get(route('portal.savings.index'));

        $response->assertOk();
        $response->assertSee($accountA->account_number);
    }

    public function test_member_cannot_view_another_members_savings_history(): void
    {
        [$userA] = $this->anggotaWithMember();
        [, $memberB] = $this->anggotaWithMember();
        $accountB = SavingsAccount::factory()->create(['member_id' => $memberB->id]);

        $this->actingAs($userA)->get(route('portal.savings.history', $accountB))->assertForbidden();
    }

    public function test_member_cannot_view_another_members_loan_schedule(): void
    {
        [$userA] = $this->anggotaWithMember();
        [, $memberB] = $this->anggotaWithMember();
        $loanB = Loan::factory()->create(['member_id' => $memberB->id, 'status' => 'dicairkan']);

        $this->actingAs($userA)->get(route('portal.loans.schedule', $loanB))->assertForbidden();
    }

    public function test_member_can_view_own_loan_schedule(): void
    {
        [$user, $member] = $this->anggotaWithMember();
        $loan = Loan::factory()->create(['member_id' => $member->id, 'status' => 'dicairkan']);
        LoanSchedule::query()->create([
            'loan_id' => $loan->id, 'installment_number' => 1, 'due_date' => now()->addDay(),
            'principal_amount' => 100000, 'interest_amount' => 10000, 'total_amount' => 110000,
            'paid_amount' => 0, 'status' => 'belum_bayar',
        ]);

        $response = $this->actingAs($user)->get(route('portal.loans.schedule', $loan));

        $response->assertOk();
        $response->assertSee('110.000');
    }

    public function test_member_loan_application_is_always_submitted_under_their_own_member_regardless_of_payload(): void
    {
        [$user, $member] = $this->anggotaWithMember();
        [, $otherMember] = $this->anggotaWithMember();
        $product = LoanProduct::factory()->create(['is_active' => true, 'min_plafon' => 0, 'max_plafon' => 10000000]);

        $response = $this->actingAs($user)->post(route('portal.loan-application.store'), [
            'member_id' => $otherMember->id, // ignored — no such field is even validated
            'loan_product_id' => $product->id,
            'principal_amount' => 3000000,
            'tenor_days' => 12,
        ]);

        $response->assertRedirect(route('portal.loan-application.create'));

        $loan = Loan::query()->latest()->firstOrFail();
        $this->assertEquals($member->id, $loan->member_id);
        $this->assertNotEquals($otherMember->id, $loan->member_id);
    }

    public function test_member_cannot_submit_a_withdrawal_request_against_another_members_account(): void
    {
        [$userA] = $this->anggotaWithMember();
        [, $memberB] = $this->anggotaWithMember();
        $accountB = SavingsAccount::factory()->create(['member_id' => $memberB->id, 'balance' => 500000]);

        $this->actingAs($userA)->post(route('portal.withdrawal-request.store'), [
            'savings_account_id' => $accountB->id,
            'amount' => 100000,
        ])->assertForbidden();

        $this->assertDatabaseCount('savings_withdrawal_requests', 0);
    }

    public function test_member_can_submit_a_withdrawal_request_for_their_own_account(): void
    {
        [$user, $member] = $this->anggotaWithMember();
        $account = SavingsAccount::factory()->create(['member_id' => $member->id, 'balance' => 500000]);

        $response = $this->actingAs($user)->post(route('portal.withdrawal-request.store'), [
            'savings_account_id' => $account->id,
            'amount' => 100000,
        ]);

        $response->assertRedirect(route('portal.withdrawal-request.create'));
        $this->assertDatabaseHas('savings_withdrawal_requests', [
            'savings_account_id' => $account->id,
            'member_id' => $member->id,
            'status' => 'menunggu',
        ]);
    }

    public function test_member_can_view_own_deposit_page_but_not_another_members(): void
    {
        [$userA, $memberA] = $this->anggotaWithMember();
        [, $memberB] = $this->anggotaWithMember();
        $accountA = SavingsAccount::factory()->create(['member_id' => $memberA->id]);
        $accountB = SavingsAccount::factory()->create(['member_id' => $memberB->id]);

        $this->actingAs($userA)->get(route('portal.savings.deposit.create', $accountA))->assertOk();
        $this->actingAs($userA)->get(route('portal.savings.deposit.create', $accountB))->assertForbidden();
    }

    public function test_member_can_view_own_loan_repayment_page_but_not_another_members(): void
    {
        [$userA, $memberA] = $this->anggotaWithMember();
        [, $memberB] = $this->anggotaWithMember();
        $loanA = Loan::factory()->create(['member_id' => $memberA->id, 'status' => 'dicairkan']);
        $loanB = Loan::factory()->create(['member_id' => $memberB->id, 'status' => 'dicairkan']);

        $this->actingAs($userA)->get(route('portal.loans.repayment.create', $loanA))->assertOk();
        $this->actingAs($userA)->get(route('portal.loans.repayment.create', $loanB))->assertForbidden();
    }
}
