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
 *
 * Tenor bersatuan HARI (bukan bulan) — koperasi ini bermodel harian.
 */
class LoanService
{
    public function __construct(private readonly LoanScheduleCalculator $scheduleCalculator) {}

    public function submitApplication(
        Member $member,
        LoanProduct $product,
        float $principal,
        int $tenorDays,
        int $branchId,
        int $createdBy,
    ): Loan {
        $this->assertWithinProductLimits($product, $principal, $tenorDays);

        $rate = $product->rateAt();

        return DB::transaction(function () use ($member, $product, $principal, $tenorDays, $branchId, $createdBy, $rate) {
            return Loan::query()->create([
                'branch_id' => $branchId,
                'member_id' => $member->id,
                'loan_product_id' => $product->id,
                'loan_number' => $this->generateLoanNumber($product),
                'principal_amount' => $principal,
                'tenor_days' => $tenorDays,
                'interest_rate_percentage' => $rate?->rate_percentage ?? 0,
                'required_approval_count' => $product->requiredApprovalCountFor($principal),
                'status' => 'diajukan',
                'created_by' => $createdBy,
                'submitted_at' => now()->toDateString(),
            ]);
        });
    }

    /**
     * Pinjaman instan dari penjualan POS "hutang" (kasir langsung
     * mengaktifkan, tanpa alur persetujuan berjenjang — disengaja untuk
     * fitur ini, level kepercayaan sama seperti Tunai/Potong Simpanan).
     *
     * WAJIB dipanggil dari dalam DB::transaction() yang sudah terbuka
     * (PosSaleService::sell()) — method ini TIDAK membuka transaksi
     * sendiri, dan TIDAK PERNAH memposting jurnal: kaki piutangnya jadi
     * bagian dari satu jurnal gabungan penjualan POS yang diposting oleh
     * pemanggil (beda dengan LoanApprovalService::disburse() yang
     * memposting jurnal Dr Piutang/Cr Kas terpisah, karena pencairan
     * pinjaman normal = keluar uang tunai — hutang POS tidak ada
     * pergerakan kas sama sekali).
     */
    public function originateInstantly(
        Member $member,
        LoanProduct $product,
        float $principal,
        int $tenorDays,
        int $branchId,
        int $createdBy,
    ): Loan {
        $this->assertWithinProductLimits($product, $principal, $tenorDays);

        $ratePercentage = (float) ($product->rateAt()?->rate_percentage ?? 0);

        $loan = Loan::query()->create([
            'branch_id' => $branchId,
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'loan_number' => $this->generateLoanNumber($product),
            'principal_amount' => $principal,
            'tenor_days' => $tenorDays,
            'interest_rate_percentage' => $ratePercentage,
            'provision_fee_amount' => 0,
            'required_approval_count' => $product->requiredApprovalCountFor($principal),
            'status' => 'dicairkan',
            'collectibility' => 'lancar',
            'created_by' => $createdBy,
            'submitted_at' => now()->toDateString(),
            'disbursed_at' => now()->toDateString(),
        ]);

        foreach ($this->scheduleCalculator->calculate($principal, $tenorDays, $ratePercentage, $product->calculation_method, now()) as $row) {
            $loan->schedules()->create([
                'installment_number' => $row['installment_number'],
                'due_date' => $row['due_date'],
                'principal_amount' => $row['principal_amount'],
                'interest_amount' => $row['interest_amount'],
                'total_amount' => $row['total_amount'],
            ]);
        }

        return $loan->fresh('schedules');
    }

    private function assertWithinProductLimits(LoanProduct $product, float $principal, int $tenorDays): void
    {
        if ($principal < (float) $product->min_plafon || $principal > (float) $product->max_plafon) {
            throw InvalidLoanApplicationException::plafonOutOfRange($principal, (float) $product->min_plafon, (float) $product->max_plafon);
        }

        if ($tenorDays < $product->min_tenor_days || $tenorDays > $product->max_tenor_days) {
            throw InvalidLoanApplicationException::tenorOutOfRange($tenorDays, $product->min_tenor_days, $product->max_tenor_days);
        }
    }

    private function generateLoanNumber(LoanProduct $product): string
    {
        do {
            $candidate = strtoupper($product->code).'-'.now()->format('ymd').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Loan::query()->where('loan_number', $candidate)->exists());

        return $candidate;
    }
}
