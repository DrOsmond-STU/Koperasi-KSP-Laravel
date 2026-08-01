<?php

namespace Tests\Feature\Loans;

use App\Models\Loan;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Loans\LoanApprovalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_creator_can_cancel_own_loan_disbursement(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $creator = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $creator->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $creator->id, 'scope_type' => 'all']);

        $approver = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $approver->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $approver->id, 'scope_type' => 'all']);

        $loan = Loan::factory()->create(['created_by' => $creator->id, 'required_approval_count' => 1]);
        app(LoanApprovalService::class)->approve($loan, $approver);

        $response = $this->actingAs($creator)->post(route('admin.pinjaman.cancel', $loan), [
            'reason' => 'Salah input plafon',
        ]);

        $response->assertRedirect(route('admin.pinjaman.index'));
        $this->assertEquals('dibatalkan', $loan->fresh()->status);
    }

    public function test_role_without_delete_permission_cannot_cancel_a_disbursement(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $creator = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $creator->assignRole('petugas_kredit');
        UserBranchScope::query()->create(['user_id' => $creator->id, 'scope_type' => 'all']);

        $approver = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $approver->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $approver->id, 'scope_type' => 'all']);

        $loan = Loan::factory()->create(['created_by' => $creator->id, 'required_approval_count' => 1]);
        app(LoanApprovalService::class)->approve($loan, $approver);

        $this->actingAs($creator)->post(route('admin.pinjaman.cancel', $loan), [
            'reason' => 'Coba batalkan',
        ])->assertForbidden();

        $this->assertEquals('dicairkan', $loan->fresh()->status);
    }

    public function test_other_manajer_at_a_different_scope_can_still_cancel_someone_elses_disbursement(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $creator = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $creator->assignRole('petugas_kredit');
        UserBranchScope::query()->create(['user_id' => $creator->id, 'scope_type' => 'all']);

        $approver = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $approver->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $approver->id, 'scope_type' => 'all']);

        $manajer = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $manajer->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $manajer->id, 'scope_type' => 'all']);

        $loan = Loan::factory()->create(['created_by' => $creator->id, 'required_approval_count' => 1]);
        app(LoanApprovalService::class)->approve($loan, $approver);

        $response = $this->actingAs($manajer)->post(route('admin.pinjaman.cancel', $loan), [
            'reason' => 'Dibatalkan oleh manajer',
        ]);

        $response->assertRedirect(route('admin.pinjaman.index'));
        $this->assertEquals('dibatalkan', $loan->fresh()->status);
    }
}
