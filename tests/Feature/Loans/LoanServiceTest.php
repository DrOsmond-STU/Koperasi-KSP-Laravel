<?php

namespace Tests\Feature\Loans;

use App\Exceptions\Loans\InvalidLoanApplicationException;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\User;
use App\Services\Loans\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_plafon_outside_product_range_is_rejected(): void
    {
        $product = LoanProduct::factory()->create(['min_plafon' => 1000000, 'max_plafon' => 5000000]);
        $member = Member::factory()->create();
        $user = User::factory()->create();

        $this->expectException(InvalidLoanApplicationException::class);

        app(LoanService::class)->submitApplication($member, $product, 10_000_000, 12, $member->branch_id, $user->id);
    }

    public function test_tenor_outside_product_range_is_rejected(): void
    {
        $product = LoanProduct::factory()->create(['min_tenor_days' => 6, 'max_tenor_days' => 12]);
        $member = Member::factory()->create();
        $user = User::factory()->create();

        $this->expectException(InvalidLoanApplicationException::class);

        app(LoanService::class)->submitApplication($member, $product, 5_000_000, 36, $member->branch_id, $user->id);
    }

    public function test_valid_application_snapshots_current_rate_and_required_approvals(): void
    {
        $product = LoanProduct::factory()->create(['approval_threshold' => 10_000_000]);
        $member = Member::factory()->create();
        $user = User::factory()->create();

        $loan = app(LoanService::class)->submitApplication($member, $product, 20_000_000, 12, $member->branch_id, $user->id);

        $this->assertEquals('diajukan', $loan->status);
        $this->assertEquals(12.0, (float) $loan->interest_rate_percentage);
        $this->assertEquals(2, $loan->required_approval_count); // above threshold -> 2 approvals
    }

    public function test_application_below_threshold_needs_only_one_approval(): void
    {
        $product = LoanProduct::factory()->create(['approval_threshold' => 10_000_000]);
        $member = Member::factory()->create();
        $user = User::factory()->create();

        $loan = app(LoanService::class)->submitApplication($member, $product, 2_000_000, 12, $member->branch_id, $user->id);

        $this->assertEquals(1, $loan->required_approval_count);
    }

    public function test_originate_instantly_creates_a_dicairkan_loan_with_schedule(): void
    {
        $product = LoanProduct::factory()->create(['min_plafon' => 0, 'min_tenor_days' => 3, 'max_tenor_days' => 60]);
        $member = Member::factory()->create();
        $user = User::factory()->create();

        $loan = app(LoanService::class)->originateInstantly($member, $product, 3_000_000, 6, $member->branch_id, $user->id);

        $this->assertEquals('dicairkan', $loan->status);
        $this->assertEquals('lancar', $loan->collectibility);
        $this->assertNotNull($loan->disbursed_at);
        $this->assertEquals('0.00', $loan->provision_fee_amount);
        $this->assertCount(6, $loan->schedules);
    }

    public function test_originate_instantly_rejects_out_of_range_plafon(): void
    {
        $product = LoanProduct::factory()->create(['min_plafon' => 1000000, 'max_plafon' => 5000000]);
        $member = Member::factory()->create();
        $user = User::factory()->create();

        $this->expectException(InvalidLoanApplicationException::class);

        app(LoanService::class)->originateInstantly($member, $product, 10_000_000, 6, $member->branch_id, $user->id);
    }

    public function test_originate_instantly_rejects_out_of_range_tenor(): void
    {
        $product = LoanProduct::factory()->create(['min_plafon' => 0, 'min_tenor_days' => 6, 'max_tenor_days' => 12]);
        $member = Member::factory()->create();
        $user = User::factory()->create();

        $this->expectException(InvalidLoanApplicationException::class);

        app(LoanService::class)->originateInstantly($member, $product, 1_000_000, 36, $member->branch_id, $user->id);
    }
}
