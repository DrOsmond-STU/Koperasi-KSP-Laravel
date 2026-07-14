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
        $product = LoanProduct::factory()->create(['min_tenor_months' => 6, 'max_tenor_months' => 12]);
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
}
