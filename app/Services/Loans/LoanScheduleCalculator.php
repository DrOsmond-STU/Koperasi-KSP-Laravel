<?php

namespace App\Services\Loans;

use Illuminate\Support\Carbon;

/**
 * Pure calculation, no persistence — LoanApprovalService::disburse() turns
 * the output into `loan_schedules` rows. Kept separate from persistence so
 * the three methods (PRD §7.3) can be unit-tested without a database.
 */
class LoanScheduleCalculator
{
    /**
     * @return array<int, array{installment_number: int, due_date: Carbon, principal_amount: float, interest_amount: float, total_amount: float}>
     */
    public function calculate(
        float $principal,
        int $tenorMonths,
        float $annualRatePercentage,
        string $method,
        Carbon $disbursementDate,
    ): array {
        return match ($method) {
            'flat' => $this->flat($principal, $tenorMonths, $annualRatePercentage, $disbursementDate),
            'efektif' => $this->efektif($principal, $tenorMonths, $annualRatePercentage, $disbursementDate),
            'anuitas' => $this->anuitas($principal, $tenorMonths, $annualRatePercentage, $disbursementDate),
            default => throw new \InvalidArgumentException("Metode perhitungan tidak dikenal: {$method}"),
        };
    }

    private function monthlyRate(float $annualRatePercentage): float
    {
        return $annualRatePercentage / 100 / 12;
    }

    private function flat(float $principal, int $tenor, float $annualRate, Carbon $start): array
    {
        $monthlyRate = $this->monthlyRate($annualRate);
        $principalPerMonth = round($principal / $tenor, 2);
        $interestPerMonth = round($principal * $monthlyRate, 2);

        $rows = [];
        $principalPaid = 0.0;

        for ($i = 1; $i <= $tenor; $i++) {
            $isLast = $i === $tenor;
            $principalAmount = $isLast ? round($principal - $principalPaid, 2) : $principalPerMonth;
            $principalPaid += $principalAmount;

            $rows[] = $this->row($i, $start, $principalAmount, $interestPerMonth);
        }

        return $rows;
    }

    private function efektif(float $principal, int $tenor, float $annualRate, Carbon $start): array
    {
        $monthlyRate = $this->monthlyRate($annualRate);
        $principalPerMonth = round($principal / $tenor, 2);

        $rows = [];
        $remaining = $principal;

        for ($i = 1; $i <= $tenor; $i++) {
            $isLast = $i === $tenor;
            $principalAmount = $isLast ? round($remaining, 2) : $principalPerMonth;
            $interestAmount = round($remaining * $monthlyRate, 2);

            $rows[] = $this->row($i, $start, $principalAmount, $interestAmount);
            $remaining = round($remaining - $principalAmount, 2);
        }

        return $rows;
    }

    private function anuitas(float $principal, int $tenor, float $annualRate, Carbon $start): array
    {
        $monthlyRate = $this->monthlyRate($annualRate);

        $payment = $monthlyRate == 0.0
            ? $principal / $tenor
            : $principal * $monthlyRate * (1 + $monthlyRate) ** $tenor / ((1 + $monthlyRate) ** $tenor - 1);

        $rows = [];
        $remaining = $principal;

        for ($i = 1; $i <= $tenor; $i++) {
            $isLast = $i === $tenor;
            $interestAmount = round($remaining * $monthlyRate, 2);
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
            'due_date' => $start->copy()->addMonthsNoOverflow($installmentNumber),
            'principal_amount' => $principalAmount,
            'interest_amount' => $interestAmount,
            'total_amount' => round($principalAmount + $interestAmount, 2),
        ];
    }

    /**
     * Pinjaman anggota koperasi ini HARIAN (mis. 100 hari, 200 hari), bukan
     * bulanan — laporan staf 24 Agu 2026: "perhitungan jasa nya salah...
     * sekarang yang ada adalah perhitungan nya bulan x 12 jadi tahunan.
     * padahal pinjaman anggota itu harian". Jadwal 1 baris PER HARI (100
     * hari = 100 baris), dan $flatRatePercentage adalah tarif jasa FLAT
     * untuk SELURUH tenor (bukan per tahun) — jasa harian = (pokok ×
     * tarif flat) ÷ jumlah hari tenor, disebar rata ke tiap hari.
     *
     * Method terpisah dari calculate() (bulanan) di atas — dipertahankan
     * apa adanya untuk pinjaman yang sudah diajukan/berjalan SEBELUM
     * perbaikan ini (lihat LoanApprovalService::disburse()), supaya tidak
     * menginterpretasi ulang tenor pinjaman lama yang memang bulanan.
     *
     * @return array<int, array{installment_number: int, due_date: Carbon, principal_amount: float, interest_amount: float, total_amount: float}>
     */
    public function calculateDaily(
        float $principal,
        int $tenorDays,
        float $flatRatePercentage,
        string $method,
        Carbon $disbursementDate,
    ): array {
        return match ($method) {
            'flat' => $this->flatDaily($principal, $tenorDays, $flatRatePercentage, $disbursementDate),
            'efektif' => $this->efektifDaily($principal, $tenorDays, $flatRatePercentage, $disbursementDate),
            'anuitas' => $this->anuitasDaily($principal, $tenorDays, $flatRatePercentage, $disbursementDate),
            default => throw new \InvalidArgumentException("Metode perhitungan tidak dikenal: {$method}"),
        };
    }

    /** Tarif per hari turunan dari tarif flat SELURUH tenor — bukan tarif tahunan dibagi 12 seperti monthlyRate(). */
    private function dailyRate(float $flatRatePercentage, int $tenorDays): float
    {
        return $tenorDays > 0 ? $flatRatePercentage / 100 / $tenorDays : 0.0;
    }

    private function flatDaily(float $principal, int $tenor, float $flatRate, Carbon $start): array
    {
        $dailyRate = $this->dailyRate($flatRate, $tenor);
        $principalPerDay = round($principal / $tenor, 2);
        $interestPerDay = round($principal * $dailyRate, 2);

        $rows = [];
        $principalPaid = 0.0;

        for ($i = 1; $i <= $tenor; $i++) {
            $isLast = $i === $tenor;
            $principalAmount = $isLast ? round($principal - $principalPaid, 2) : $principalPerDay;
            $principalPaid += $principalAmount;

            $rows[] = $this->rowDaily($i, $start, $principalAmount, $interestPerDay);
        }

        return $rows;
    }

    private function efektifDaily(float $principal, int $tenor, float $flatRate, Carbon $start): array
    {
        $dailyRate = $this->dailyRate($flatRate, $tenor);
        $principalPerDay = round($principal / $tenor, 2);

        $rows = [];
        $remaining = $principal;

        for ($i = 1; $i <= $tenor; $i++) {
            $isLast = $i === $tenor;
            $principalAmount = $isLast ? round($remaining, 2) : $principalPerDay;
            $interestAmount = round($remaining * $dailyRate, 2);

            $rows[] = $this->rowDaily($i, $start, $principalAmount, $interestAmount);
            $remaining = round($remaining - $principalAmount, 2);
        }

        return $rows;
    }

    private function anuitasDaily(float $principal, int $tenor, float $flatRate, Carbon $start): array
    {
        $dailyRate = $this->dailyRate($flatRate, $tenor);

        $payment = $dailyRate == 0.0
            ? $principal / $tenor
            : $principal * $dailyRate * (1 + $dailyRate) ** $tenor / ((1 + $dailyRate) ** $tenor - 1);

        $rows = [];
        $remaining = $principal;

        for ($i = 1; $i <= $tenor; $i++) {
            $isLast = $i === $tenor;
            $interestAmount = round($remaining * $dailyRate, 2);
            $principalAmount = $isLast ? round($remaining, 2) : round($payment - $interestAmount, 2);

            $rows[] = $this->rowDaily($i, $start, $principalAmount, $interestAmount);
            $remaining = round($remaining - $principalAmount, 2);
        }

        return $rows;
    }

    private function rowDaily(int $installmentNumber, Carbon $start, float $principalAmount, float $interestAmount): array
    {
        return [
            'installment_number' => $installmentNumber,
            'due_date' => $start->copy()->addDays($installmentNumber),
            'principal_amount' => $principalAmount,
            'interest_amount' => $interestAmount,
            'total_amount' => round($principalAmount + $interestAmount, 2),
        ];
    }
}
