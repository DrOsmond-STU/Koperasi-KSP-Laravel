<?php

namespace App\Services\Loans;

use App\Exceptions\Loans\InvalidLoanApplicationException;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * Pengajuan pinjaman (PRD §8, Task 1.13). Validasi plafon/tenor terhadap
 * produk, snapshot tarif & jumlah approval yang dibutuhkan pada saat
 * pengajuan (bukan dihitung ulang nanti) agar perubahan produk di kemudian
 * hari tidak memengaruhi pengajuan yang sudah berjalan.
 */
class LoanService
{
    public function submitApplication(
        Member $member,
        LoanProduct $product,
        float $principal,
        int $tenorMonths,
        int $branchId,
        int $createdBy,
    ): Loan {
        if ($principal < (float) $product->min_plafon || $principal > (float) $product->max_plafon) {
            throw InvalidLoanApplicationException::plafonOutOfRange($principal, (float) $product->min_plafon, (float) $product->max_plafon);
        }

        if ($tenorMonths < $product->min_tenor_months || $tenorMonths > $product->max_tenor_months) {
            throw InvalidLoanApplicationException::tenorOutOfRange($tenorMonths, $product->min_tenor_months, $product->max_tenor_months);
        }

        $rate = $product->rateAt();

        return DB::transaction(function () use ($member, $product, $principal, $tenorMonths, $branchId, $createdBy, $rate) {
            return Loan::query()->create([
                'branch_id' => $branchId,
                'member_id' => $member->id,
                'loan_product_id' => $product->id,
                'loan_number' => $this->generateLoanNumber($product),
                'principal_amount' => $principal,
                'tenor_months' => $tenorMonths,
                'interest_rate_percentage' => $rate?->rate_percentage ?? 0,
                'required_approval_count' => $product->requiredApprovalCountFor($principal),
                'status' => 'diajukan',
                'created_by' => $createdBy,
                'submitted_at' => now()->toDateString(),
            ]);
        });
    }

    private function generateLoanNumber(LoanProduct $product): string
    {
        do {
            $candidate = strtoupper($product->code).'-'.now()->format('ymd').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Loan::query()->where('loan_number', $candidate)->exists());

        return $candidate;
    }
}
