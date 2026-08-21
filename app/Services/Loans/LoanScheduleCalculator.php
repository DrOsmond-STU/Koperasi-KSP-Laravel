<?php

namespace App\Services\Loans;

use Illuminate\Support\Carbon;

/**
 * Pure calculation, no persistence — LoanApprovalService::disburse() turns
 * the output into `loan_schedules` rows. Kept separate from persistence so
 * the three methods (PRD §7.3) can be unit-tested without a database.
 *
 * Tenor dan angsuran koperasi ini bermodel HARIAN (bukan bulanan). Suku
 * bunga tetap disimpan sebagai `%/tahun` di database (industri finansial
 * & regulator BI mengutip semua tarif sebagai APR tahunan), lalu di sini
 * dibagi 360 untuk mendapatkan tarif per hari. Pembagi 360 (bukan 365)
 * mengikuti konvensi BI/perbankan Indonesia — 30 hari/bulan × 12 bulan —
 * dan menjaga aritmatika tetap sederhana ketika koperasi ingin
 * membandingkan produk harian dengan produk bulanan legacy.
 */
class LoanScheduleCalculator
{
    /** Hari dalam setahun untuk konversi %/tahun → %/hari (konvensi BI 30/360). */
    private const DAYS_PER_YEAR = 360;

    /**
     * @return array<int, array{installment_number: int, due_date: Carbon, principal_amount: float, interest_amount: float, total_amount: float}>
     */
    public function calculate(
        float $principal,
        int $tenorDays,
        float $annualRatePercentage,
        string $method,
        Carbon $disbursementDate,
    ): array {
        return match ($method) {
            'flat' => $this->flat($principal, $tenorDays, $annualRatePercentage, $disbursementDate),
            'efektif' => $this->efektif($principal, $tenorDays, $annualRatePercentage, $disbursementDate),
            'anuitas' => $this->anuitas($principal, $tenorDays, $annualRatePercentage, $disbursementDate),
            default => throw new \InvalidArgumentException("Metode perhitungan tidak dikenal: {$method}"),
        };
    }

    private function dailyRate(float $annualRatePercentage): float
    {
        return $annualRatePercentage / 100 / self::DAYS_PER_YEAR;
    }

    private function flat(float $principal, int $tenor, float $annualRate, Carbon $start): array
    {
        $dailyRate = $this->dailyRate($annualRate);
        $principalPerDay = round($principal / $tenor, 2);
        $interestPerDay = round($principal * $dailyRate, 2);

        $rows = [];
        $principalPaid = 0.0;

        for ($i = 1; $i <= $tenor; $i++) {
            $isLast = $i === $tenor;
            $principalAmount = $isLast ? round($principal - $principalPaid, 2) : $principalPerDay;
            $principalPaid += $principalAmount;

            $rows[] = $this->row($i, $start, $principalAmount, $interestPerDay);
        }

        return $rows;
    }

    private function efektif(float $principal, int $tenor, float $annualRate, Carbon $start): array
    {
        $dailyRate = $this->dailyRate($annualRate);
        $principalPerDay = round($principal / $tenor, 2);

        $rows = [];
        $remaining = $principal;

        for ($i = 1; $i <= $tenor; $i++) {
            $isLast = $i === $tenor;
            $principalAmount = $isLast ? round($remaining, 2) : $principalPerDay;
            $interestAmount = round($remaining * $dailyRate, 2);

            $rows[] = $this->row($i, $start, $principalAmount, $interestAmount);
            $remaining = round($remaining - $principalAmount, 2);
        }

        return $rows;
    }

    private function anuitas(float $principal, int $tenor, float $annualRate, Carbon $start): array
    {
        $dailyRate = $this->dailyRate($annualRate);

        $payment = $dailyRate == 0.0
            ? $principal / $tenor
            : $principal * $dailyRate * (1 + $dailyRate) ** $tenor / ((1 + $dailyRate) ** $tenor - 1);

        $rows = [];
        $remaining = $principal;

        for ($i = 1; $i <= $tenor; $i++) {
            $isLast = $i === $tenor;
            $interestAmount = round($remaining * $dailyRate, 2);
            $principalAmount = $isLast ? round($remaining, 2) : round($payment - $interestAmount, 2);

            $rows[] = $this->row($i, $start, $principalAmount, $interestAmount);
            $remaining = round($remaining - $principalAmount, 2);
        }

        return $rows;
    }

    private function row(int $installmentNumber, Carbon $start, float $principalAmount, float $interestAmount): array
    {
        return [
            'installment_number' => $installmentNumber,
            // Jadwal harian: angsuran ke-N jatuh tempo N hari setelah tanggal
            // pencairan. Tidak pakai addDaysNoOverflow karena Carbon menganggap
            // "1 hari" selalu 24 jam — tidak ada kasus tepi seperti "31 Feb".
            'due_date' => $start->copy()->addDays($installmentNumber),
            'principal_amount' => $principalAmount,
            'interest_amount' => $interestAmount,
            'total_amount' => round($principalAmount + $interestAmount, 2),
        ];
    }
}
