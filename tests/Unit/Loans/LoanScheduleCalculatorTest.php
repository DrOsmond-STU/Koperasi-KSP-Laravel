<?php

namespace Tests\Unit\Loans;

use App\Services\Loans\LoanScheduleCalculator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Covers PRD §7.3 — jadwal angsuran flat/efektif/anuitas dalam bentuk
 * harian (tenor per hari, `%/tahun` dibagi 360 untuk mendapatkan tarif
 * per hari — konvensi BI 30/360).
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
        $rows = $this->calculator->calculate(12_000_000, 30, 12.0, 'flat', Carbon::parse('2026-01-01'));

        $this->assertCount(30, $rows);
        $this->assertEqualsWithDelta(12_000_000, array_sum(array_column($rows, 'principal_amount')), 0.01);

        // Flat: bunga per hari konstan.
        $interestValues = array_unique(array_column($rows, 'interest_amount'));
        $this->assertCount(1, $interestValues);
    }

    public function test_efektif_interest_declines_over_time(): void
    {
        $rows = $this->calculator->calculate(12_000_000, 30, 12.0, 'efektif', Carbon::parse('2026-01-01'));

        $this->assertCount(30, $rows);
        $this->assertEqualsWithDelta(12_000_000, array_sum(array_column($rows, 'principal_amount')), 0.01);
        $this->assertGreaterThan($rows[29]['interest_amount'], $rows[0]['interest_amount']);
    }

    public function test_anuitas_has_roughly_constant_total_installment(): void
    {
        $rows = $this->calculator->calculate(12_000_000, 30, 12.0, 'anuitas', Carbon::parse('2026-01-01'));

        $this->assertCount(30, $rows);
        $this->assertEqualsWithDelta(12_000_000, array_sum(array_column($rows, 'principal_amount')), 0.05);

        // Anuitas: total angsuran per hari (nyaris) sama, beda dari flat/efektif.
        $totals = array_column($rows, 'total_amount');
        $this->assertEqualsWithDelta($totals[0], $totals[15], 5.0);
    }

    public function test_due_dates_increment_daily_from_disbursement(): void
    {
        $rows = $this->calculator->calculate(1_000_000, 3, 12.0, 'flat', Carbon::parse('2026-01-15'));

        $this->assertEquals('2026-01-16', $rows[0]['due_date']->toDateString());
        $this->assertEquals('2026-01-17', $rows[1]['due_date']->toDateString());
        $this->assertEquals('2026-01-18', $rows[2]['due_date']->toDateString());
    }

    public function test_daily_rate_is_annual_divided_by_360(): void
    {
        // 12%/tahun × Rp 3.600.000 = 12/360 = 0.0333%/hari
        // Flat: bunga per hari = 3.600.000 × 0.000333 = Rp 1.200 (exact karena
        // 12/360 = 1/30 dan 3.600.000/30 = 120.000... tunggu, ulang:
        //   dailyRate = 12/100/360 = 0.000333...
        //   3.600.000 × 0.000333... = 1.200
        // Ya, benar Rp 1.200 per hari.
        $rows = $this->calculator->calculate(3_600_000, 10, 12.0, 'flat', Carbon::parse('2026-01-01'));

        $this->assertEqualsWithDelta(1_200.0, $rows[0]['interest_amount'], 0.01);
    }
}
