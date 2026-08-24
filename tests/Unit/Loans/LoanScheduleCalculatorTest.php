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

    /**
     * Regresi utama untuk laporan staf 24 Agu 2026: "perhitungan jasa nya
     * salah... padahal pinjaman anggota itu harian, 100hari dan 200hari".
     * Rumus dikonfirmasi user: tarif jasa FLAT untuk seluruh tenor (bukan
     * per tahun) — jasa harian = (pokok x tarif flat) / jumlah hari tenor.
     *
     * Pokok 10.000.000, tarif 10% flat, tenor 100 hari:
     * jasa total = 1.000.000, jasa harian = 10.000, pokok harian = 100.000,
     * angsuran harian = 110.000.
     */
    public function test_calculate_daily_flat_matches_the_confirmed_formula(): void
    {
        $rows = $this->calculator->calculateDaily(10_000_000, 100, 10.0, 'flat', Carbon::parse('2026-01-01'));

        $this->assertCount(100, $rows);
        $this->assertEqualsWithDelta(10_000_000, array_sum(array_column($rows, 'principal_amount')), 0.01);

        foreach ($rows as $row) {
            $this->assertEqualsWithDelta(100000.0, $row['principal_amount'], 0.01);
            $this->assertEqualsWithDelta(10000.0, $row['interest_amount'], 0.01);
            $this->assertEqualsWithDelta(110000.0, $row['total_amount'], 0.01);
        }
    }

    /** Tenor 200 hari (dua kali 100 hari) dengan tarif flat yang sama menghasilkan jasa TOTAL dua kali lipat — tapi jasa HARIAN tetap sama (tarif flat dibagi rata ke lebih banyak hari). */
    public function test_calculate_daily_flat_total_jasa_scales_linearly_with_tenor_at_the_same_flat_rate(): void
    {
        $rows100 = $this->calculator->calculateDaily(10_000_000, 100, 10.0, 'flat', Carbon::parse('2026-01-01'));
        $rows200 = $this->calculator->calculateDaily(10_000_000, 200, 10.0, 'flat', Carbon::parse('2026-01-01'));

        $totalJasa100 = array_sum(array_column($rows100, 'interest_amount'));
        $totalJasa200 = array_sum(array_column($rows200, 'interest_amount'));

        $this->assertEqualsWithDelta(1_000_000, $totalJasa100, 1.0);
        $this->assertEqualsWithDelta(1_000_000, $totalJasa200, 1.0); // tarif flat SAMA -> jasa total SAMA, bukan 2x
        $this->assertEqualsWithDelta(10000.0, $rows100[0]['interest_amount'], 0.01);
        $this->assertEqualsWithDelta(5000.0, $rows200[0]['interest_amount'], 0.01); // disebar ke lebih banyak hari -> jasa harian lebih kecil
    }

    public function test_calculate_daily_efektif_interest_declines_over_time(): void
    {
        $rows = $this->calculator->calculateDaily(10_000_000, 100, 10.0, 'efektif', Carbon::parse('2026-01-01'));

        $this->assertCount(100, $rows);
        $this->assertEqualsWithDelta(10_000_000, array_sum(array_column($rows, 'principal_amount')), 0.01);
        $this->assertGreaterThan($rows[99]['interest_amount'], $rows[0]['interest_amount']);
    }

    public function test_calculate_daily_anuitas_has_roughly_constant_total_installment(): void
    {
        $rows = $this->calculator->calculateDaily(10_000_000, 100, 10.0, 'anuitas', Carbon::parse('2026-01-01'));

        $this->assertCount(100, $rows);
        $this->assertEqualsWithDelta(10_000_000, array_sum(array_column($rows, 'principal_amount')), 0.5);

        $totals = array_column($rows, 'total_amount');
        $this->assertEqualsWithDelta($totals[0], $totals[50], 1.0);
    }

    public function test_calculate_daily_due_dates_increment_one_day_at_a_time(): void
    {
        $rows = $this->calculator->calculateDaily(1_000_000, 3, 10.0, 'flat', Carbon::parse('2026-01-15'));

        $this->assertEquals('2026-01-16', $rows[0]['due_date']->toDateString());
        $this->assertEquals('2026-01-17', $rows[1]['due_date']->toDateString());
        $this->assertEquals('2026-01-18', $rows[2]['due_date']->toDateString());
    }
}
