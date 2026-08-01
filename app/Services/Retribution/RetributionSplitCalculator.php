<?php

namespace App\Services\Retribution;

/**
 * Largest-remainder method: memecah satu nilai total (dalam cent, integer)
 * ke N kategori sesuai persentase, tanpa float, sehingga jumlah baris hasil
 * SELALU persis sama dengan $totalCents — wajib untuk
 * JournalEngine::assertBalanced() yang menegakkan Σdebit = Σcredit dalam
 * integer cents (lihat app/Services/Accounting/JournalEngine.php).
 *
 * PENTING: logika ini juga direplikasi persis di sisi klien (JS, panel
 * preview jurnal live pada form transaksi Retribusi) — kalau algoritma di
 * sini berubah, sinkronkan juga perubahannya di view staf/retribusi-upf.
 */
final class RetributionSplitCalculator
{
    /**
     * @param  array<int, array{id:int, percentage:string|float}>  $activeTypes  urut sort_order lalu id
     * @return array<int, array{id:int, amount_cents:int}>  jumlah amount_cents selalu persis $totalCents
     */
    public static function split(int $totalCents, array $activeTypes): array
    {
        if ($activeTypes === []) {
            return [];
        }

        $rows = [];
        $allocated = 0;

        foreach ($activeTypes as $index => $type) {
            $basisPoints = (int) round(((float) $type['percentage']) * 100); // 33.34% -> 3334
            $exact = $totalCents * $basisPoints; // integer murni, tanpa float
            $floorCents = intdiv($exact, 10000);
            $remainder = $exact % 10000;

            $rows[$index] = [
                'id' => $type['id'],
                'amount_cents' => $floorCents,
                'remainder' => $remainder,
            ];
            $allocated += $floorCents;
        }

        $leftover = $totalCents - $allocated; // selalu 0 <= leftover < count($activeTypes) bila total persentase = 100.00

        $order = array_keys($rows);
        usort($order, fn ($a, $b) => $rows[$b]['remainder'] <=> $rows[$a]['remainder']);

        for ($i = 0; $i < $leftover; $i++) {
            $rows[$order[$i]]['amount_cents']++;
        }

        return array_map(
            fn ($row) => ['id' => $row['id'], 'amount_cents' => $row['amount_cents']],
            array_values($rows)
        );
    }
}
