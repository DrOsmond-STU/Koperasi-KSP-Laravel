<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\Member;
use Illuminate\Support\Collection;

/**
 * Rangkuman posisi pinjaman per anggota — dipanggil oleh
 * LoanPerMemberReportController baik untuk tampilan layar maupun cetakan
 * PDF. Sumber angka tunggal supaya kolom "outstanding" & "terbayar" tidak
 * bercabang antara UI dan cetakan.
 *
 * Semua nilai uang disajikan sebagai float (bukan string bcmath) — konsumen
 * cetakan cukup number_format(); dompdf tidak memerlukan presisi lebih dari
 * ini (angka disimpan asli di database sebagai decimal:2).
 */
class LoanPerMemberReportService
{
    /**
     * @param  Member  $member
     * @param  array{status?: string|null, period_start?: string|null, period_end?: string|null}  $filters
     * @return array{
     *     member: Member,
     *     loans: Collection<int, array{
     *         loan: Loan,
     *         principal: float,
     *         paid_principal: float,
     *         paid_interest: float,
     *         paid_total: float,
     *         outstanding_principal: float,
     *         outstanding_interest: float,
     *         outstanding_total: float,
     *         repayments: Collection<int, \App\Models\LoanRepayment>,
     *     }>,
     *     totals: array{
     *         principal: float,
     *         paid_principal: float,
     *         paid_interest: float,
     *         paid_total: float,
     *         outstanding_principal: float,
     *         outstanding_interest: float,
     *         outstanding_total: float,
     *     },
     * }
     */
    public function summarize(Member $member, array $filters = []): array
    {
        $status = $filters['status'] ?? 'all';
        $periodStart = $filters['period_start'] ?? null;
        $periodEnd = $filters['period_end'] ?? null;

        $query = Loan::query()
            ->where('member_id', $member->id)
            ->whereNull('cancelled_at')
            ->with([
                'loanProduct',
                'branch',
                'schedules',
                'repayments' => fn ($q) => $q->whereNull('cancelled_at')->orderBy('created_at'),
            ])
            ->orderByDesc('disbursed_at');

        // Filter periode dari `disbursed_at`.
        if ($periodStart !== null) {
            $query->where('disbursed_at', '>=', $periodStart);
        }
        if ($periodEnd !== null) {
            $query->where('disbursed_at', '<=', $periodEnd);
        }

        $loans = $query->get()->map(function (Loan $loan) {
            // Sumber utama sisa pokok = total_amount schedule - paid_amount.
            $totalScheduled = (float) $loan->schedules->sum('total_amount');
            $paidPrincipal = (float) $loan->schedules->sum('paid_principal_amount');
            $paidInterest = (float) $loan->schedules->sum('paid_interest_amount');
            $paidTotal = (float) $loan->schedules->sum('paid_amount');
            $totalPrincipal = (float) $loan->schedules->sum('principal_amount');
            $totalInterest = (float) $loan->schedules->sum('interest_amount');

            return [
                'loan' => $loan,
                'principal' => (float) $loan->principal_amount,
                'paid_principal' => $paidPrincipal,
                'paid_interest' => $paidInterest,
                'paid_total' => $paidTotal,
                'outstanding_principal' => max(0.0, $totalPrincipal - $paidPrincipal),
                'outstanding_interest' => max(0.0, $totalInterest - $paidInterest),
                'outstanding_total' => max(0.0, $totalScheduled - $paidTotal),
                'repayments' => $loan->repayments,
            ];
        });

        // Filter status DERIVED (aktif = outstanding > 0; lunas = outstanding ≈ 0).
        $loans = match ($status) {
            'aktif' => $loans->filter(fn ($r) => $r['outstanding_total'] > 0.5)->values(),
            'lunas' => $loans->filter(fn ($r) => $r['outstanding_total'] <= 0.5)->values(),
            default => $loans->values(),
        };

        $totals = [
            'principal' => $loans->sum('principal'),
            'paid_principal' => $loans->sum('paid_principal'),
            'paid_interest' => $loans->sum('paid_interest'),
            'paid_total' => $loans->sum('paid_total'),
            'outstanding_principal' => $loans->sum('outstanding_principal'),
            'outstanding_interest' => $loans->sum('outstanding_interest'),
            'outstanding_total' => $loans->sum('outstanding_total'),
        ];

        return [
            'member' => $member,
            'loans' => $loans,
            'totals' => $totals,
        ];
    }
}
