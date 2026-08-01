<?php

namespace Tests\Feature\Prints;

use App\Models\Loan;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cetakan mandiri Portal Anggota (Fase 1-3) — sama seperti seluruh
 * controller Anggota lain, cakupan datanya SELALU $request->user()->member,
 * tidak pernah dari input. Anggota A tidak boleh cetak data anggota B lewat
 * URL langsung.
 */
class PortalPrintOwnershipTest extends TestCase
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

    public function test_member_can_print_own_savings(): void
    {
        [$user, $member] = $this->anggotaWithMember();
        SavingsAccount::factory()->create(['member_id' => $member->id]);

        $response = $this->actingAs($user)->get(route('portal.print.savings'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_member_can_print_own_loans(): void
    {
        [$user] = $this->anggotaWithMember();

        $response = $this->actingAs($user)->get(route('portal.print.loans'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_member_cannot_print_another_members_loan_schedule(): void
    {
        [$userA] = $this->anggotaWithMember();
        [, $memberB] = $this->anggotaWithMember();
        $loanB = Loan::factory()->create(['member_id' => $memberB->id, 'status' => 'dicairkan']);

        $this->actingAs($userA)->get(route('portal.print.loans.schedule', $loanB))->assertForbidden();
    }

    public function test_member_can_print_own_loan_schedule(): void
    {
        [$user, $member] = $this->anggotaWithMember();
        $loan = Loan::factory()->create(['member_id' => $member->id, 'status' => 'dicairkan']);

        $response = $this->actingAs($user)->get(route('portal.print.loans.schedule', $loan));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
