<?php

namespace Tests\Unit\Loans;

use App\Services\Loans\LoanScheduleCalculator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Covers PRD §7.3 — jadwal angsuran flat/efektif/anuitas.
 */
class LoanScheduleCalculatorTest extends TestCase
{
    private LoanScheduleCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new LoanScheduleCalculator;
    }

    public function test_flat_produces_correct_number_of_rows_and_sums_to_principal(): void
    {
        $rows = $this->calculator->calculate(12_000_000, 12, 12.0, 'flat', Carbon::parse('2026-01-01'));

        $this->assertCount(12, $rows);
        $this->assertEqualsWithDelta(12_000_000, array_sum(array_column($rows, 'principal_amount')), 0.01);

        // Flat: interest is constant every month.
        $interestValues = array_unique(array_column($rows, 'interest_amount'));
        $this->assertCount(1, $interestValues);
    }

    public function test_efektif_interest_declines_over_time(): void
    {
        $rows = $this->calculator->calculate(12_000_000, 12, 12.0, 'efektif', Carbon::parse('2026-01-01'));

        $this->assertCount(12, $rows);
        $this->assertEqualsWithDelta(12_000_000, array_sum(array_column($rows, 'principal_amount')), 0.01);
        $this->assertGreaterThan($rows[11]['interest_amount'], $rows[0]['interest_amount']);
    }

    public function test_anuitas_has_roughly_constant_total_installment(): void
    {
        $rows = $this->calculator->calculate(12_000_000, 12, 12.0, 'anuitas', Carbon::parse('2026-01-01'));

        $this->assertCount(12, $rows);
        $this->assertEqualsWithDelta(12_000_000, array_sum(array_column($rows, 'principal_amount')), 0.05);

        // Anuitas: total installment is (nearly) the same every month, unlike flat/efektif.
        $totals = array_column($rows, 'total_amount');
        $this->assertEqualsWithDelta($totals[0], $totals[5], 5.0);
    }

    public function test_due_dates_increment_monthly_from_disbursement(): void
    {
        $rows = $this->calculator->calculate(1_000_000, 3, 12.0, 'flat', Carbon::parse('2026-01-15'));

        $this->assertEquals('2026-02-15', $rows[0]['due_date']->toDateString());
        $this->assertEquals('2026-03-15', $rows[1]['due_date']->toDateString());
        $this->assertEquals('2026-04-15', $rows[2]['due_date']->toDateString());
    }
}
