<?php

namespace Tests\Unit\Retribution;

use App\Services\Retribution\RetributionSplitCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Covers modul Retribusi (UPF): satu setoran total harus terpecah persis
 * (tanpa selisih rupiah) ke seluruh kategori aktif, karena
 * JournalEngine::assertBalanced() menegakkan Σdebit = Σcredit dalam integer
 * cents.
 */
class RetributionSplitCalculatorTest extends TestCase
{
    public function test_worked_example_reconciles_exactly(): void
    {
        // Rp 33.333,00 dipecah 33.34% / 33.33% / 33.33% -> harus persis balik ke Rp 33.333,00.
        $totalCents = 3_333_300; // Rp 33,333.00
        $activeTypes = [
            ['id' => 1, 'percentage' => '33.34'],
            ['id' => 2, 'percentage' => '33.33'],
            ['id' => 3, 'percentage' => '33.33'],
        ];

        $result = RetributionSplitCalculator::split($totalCents, $activeTypes);

        $byId = collect($result)->keyBy('id');
        $this->assertSame(1_111_322, $byId[1]['amount_cents']);
        $this->assertSame(1_110_989, $byId[2]['amount_cents']);
        $this->assertSame(1_110_989, $byId[3]['amount_cents']);
        $this->assertSame($totalCents, array_sum(array_column($result, 'amount_cents')));
    }

    public function test_single_active_type_at_100_percent_returns_full_amount(): void
    {
        $result = RetributionSplitCalculator::split(500_000, [['id' => 7, 'percentage' => '100.00']]);

        $this->assertSame([['id' => 7, 'amount_cents' => 500_000]], $result);
    }

    public function test_empty_active_types_returns_empty_array(): void
    {
        $this->assertSame([], RetributionSplitCalculator::split(500_000, []));
    }

    public function test_seven_way_split_reconciles_exactly(): void
    {
        // 7 kategori masing-masing 14.28% + 1 kategori 14.32% = 100.00%, sengaja tidak rata.
        $activeTypes = [
            ['id' => 1, 'percentage' => '14.28'],
            ['id' => 2, 'percentage' => '14.28'],
            ['id' => 3, 'percentage' => '14.28'],
            ['id' => 4, 'percentage' => '14.28'],
            ['id' => 5, 'percentage' => '14.28'],
            ['id' => 6, 'percentage' => '14.28'],
            ['id' => 7, 'percentage' => '14.32'],
        ];

        $result = RetributionSplitCalculator::split(1_000_000, $activeTypes);

        $this->assertSame(1_000_000, array_sum(array_column($result, 'amount_cents')));
        $this->assertCount(7, $result);
    }

    /**
     * Fuzz: untuk kombinasi persentase acak yang tetap total 100.00%, dan
     * berbagai nilai total, hasil split harus selalu persis reconcile.
     */
    public function test_fuzz_random_percentage_splits_always_reconcile_exactly(): void
    {
        for ($trial = 0; $trial < 50; $trial++) {
            $typeCount = random_int(2, 8);
            $percentages = $this->randomPercentagesSummingTo100($typeCount);

            $activeTypes = [];
            foreach ($percentages as $index => $percentage) {
                $activeTypes[] = ['id' => $index + 1, 'percentage' => number_format($percentage, 2, '.', '')];
            }

            $totalCents = random_int(1, 500_000_000);

            $result = RetributionSplitCalculator::split($totalCents, $activeTypes);

            $this->assertSame(
                $totalCents,
                array_sum(array_column($result, 'amount_cents')),
                "Fuzz trial {$trial} failed to reconcile for totalCents={$totalCents}, percentages=".json_encode($percentages)
            );
        }
    }

    /**
     * @return array<int, float>
     */
    private function randomPercentagesSummingTo100(int $count): array
    {
        $cuts = [];
        for ($i = 0; $i < $count - 1; $i++) {
            $cuts[] = round(random_int(0, 10000) / 100, 2);
        }
        sort($cuts);

        $percentages = [];
        $previous = 0.0;
        foreach ($cuts as $cut) {
            $percentages[] = round($cut - $previous, 2);
            $previous = $cut;
        }
        $percentages[] = round(100.0 - $previous, 2);

        return $percentages;
    }
}
