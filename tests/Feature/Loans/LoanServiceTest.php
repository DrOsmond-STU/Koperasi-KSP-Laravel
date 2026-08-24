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

        app(LoanService::class)->submitApplication($member, $product, 10_000_000, 100, $member->branch_id, $user->id);
    }

    public function test_tenor_outside_product_range_is_rejected(): void
    {
        $product = LoanProduct::factory()->create(['min_tenor_days' => 100, 'max_tenor_days' => 200]);
        $member = Member::factory()->create();
        $user = User::factory()->create();

        $this->expectException(InvalidLoanApplicationException::class);

        app(LoanService::class)->submitApplication($member, $product, 5_000_000, 300, $member->branch_id, $user->id);
    }

    /**
     * Produk bersatuan 'bulan' (mis. Piutang Karyawan, potong gaji bulanan)
     * TETAP bisa dipakai untuk pengajuan baru — bukan diusangkan oleh
     * produk 'hari' (mis. Pinjaman Anggota). Dua model bisnis hidup
     * berdampingan, dibedakan lewat LoanProduct::usesDailyTenor().
     */
    public function test_application_against_a_monthly_unit_product_is_accepted_and_snapshots_the_unit(): void
    {
        $product = LoanProduct::factory()->create(['tenor_unit' => 'bulan', 'min_tenor_days' => 3, 'max_tenor_days' => 24]);
        $member = Member::factory()->create();
        $user = User::factory()->create();

        $loan = app(LoanService::class)->submitApplication($member, $product, 5_000_000, 12, $member->branch_id, $user->id);

        $this->assertEquals('diajukan', $loan->status);
        $this->assertEquals(12, $loan->tenor_days);
        $this->assertEquals('bulan', $loan->tenor_unit);
        $this->assertFalse($loan->usesDailyTenor());
    }

    public function test_valid_application_snapshots_current_rate_and_required_approvals(): void
    {
        $product = LoanProduct::factory()->create(['approval_threshold' => 10_000_000]);
        $member = Member::factory()->create();
        $user = User::factory()->create();

        $loan = app(LoanService::class)->submitApplication($member, $product, 20_000_000, 100, $member->branch_id, $user->id);

        $this->assertEquals('diajukan', $loan->status);
        $this->assertEquals(100, $loan->tenor_days);
        $this->assertEquals('hari', $loan->tenor_unit);
        $this->assertEquals(12.0, (float) $loan->interest_rate_percentage);
        $this->assertEquals(2, $loan->required_approval_count); // above threshold -> 2 approvals
    }

    public function test_application_below_threshold_needs_only_one_approval(): void
    {
        $product = LoanProduct::factory()->create(['approval_threshold' => 10_000_000]);
        $member = Member::factory()->create();
        $user = User::factory()->create();

        $loan = app(LoanService::class)->submitApplication($member, $product, 2_000_000, 100, $member->branch_id, $user->id);

        $this->assertEquals(1, $loan->required_approval_count);
    }

    public function test_originate_instantly_creates_a_dicairkan_loan_with_schedule(): void
    {
        $product = LoanProduct::factory()->create(['min_plafon' => 0]);
        $member = Member::factory()->create();
        $user = User::factory()->create();

        $loan = app(LoanService::class)->originateInstantly($member, $product, 3_000_000, 100, $member->branch_id, $user->id);

        $this->assertEquals('dicairkan', $loan->status);
        $this->assertEquals('lancar', $loan->collectibility);
        $this->assertNotNull($loan->disbursed_at);
        $this->assertEquals('0.00', $loan->provision_fee_amount);
        $this->assertCount(100, $loan->schedules);
    }

    public function test_originate_instantly_rejects_out_of_range_plafon(): void
    {
        $product = LoanProduct::factory()->create(['min_plafon' => 1000000, 'max_plafon' => 5000000]);
        $member = Member::factory()->create();
        $user = User::factory()->create();

        $this->expectException(InvalidLoanApplicationException::class);

        app(LoanService::class)->originateInstantly($member, $product, 10_000_000, 100, $member->branch_id, $user->id);
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
