<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\LoanRepayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Perbaikan mandiri untuk pinjaman aktif ('dicairkan') yang loan_schedules-
 * nya kosong — gejalanya: Saldo Outstanding tampil Rp 0 di /staf/angsuran
 * (padahal belum lunas), dan Bayar Angsuran Mandiri lewat Portal selalu
 * ditolak "overpayment" (LoanRepaymentGatewayService::request() juga
 * lewat previewAllocation(), yang bergantung penuh pada loan_schedules).
 *
 * Ditemukan 27 Agu 2026: OpeningBalanceLockService::materializeLoans()
 * membuat Loan langsung berstatus 'dicairkan' saat migrasi saldo awal,
 * tapi HANYA membuat baris loan_schedules dari opening_balance_installments
 * milik baris itu — kalau baris installments-nya tidak pernah dientri saat
 * migrasi, Loan tetap aktif tapi tanpa jadwal sama sekali. 79 dari 142
 * pinjaman aktif kena kasus ini pada insiden pertama. Kelas ini membekukan
 * perbaikan yang saat itu dijalankan manual jadi tool permanen, supaya
 * kejadian serupa berikutnya (loan lain, migrasi lain, sebab lain yang
 * berujung sama: loan aktif tanpa jadwal) bisa dideteksi & diperbaiki
 * sendiri oleh pengurus lewat admin.pinjaman.perbaikan-jadwal, tanpa
 * investigasi manual ke database lagi.
 *
 * Strategi (tidak pernah mengarang angka):
 *  1. Bangun jadwal TEORITIS penuh dari principal_amount/tenor_days/
 *     interest_rate_percentage yang SUDAH tersnapshot di pinjaman itu
 *     sendiri (LoanScheduleCalculator — persis yang dipakai
 *     LoanApprovalService::disburse() & LoanRepaymentService::
 *     normalInstallment()).
 *  2. "Konsumsi" jadwal itu dari depan (tertua dulu, jasa dulu per baris —
 *     pola yang sama dengan LoanRepaymentService::previewAllocation())
 *     sebesar TOTAL yang SUDAH tercatat nyata di loan_repayments untuk
 *     pinjaman itu (principal_portion/interest_portion, tidak termasuk
 *     yang dibatalkan) — supaya outstanding hasil akhir = principal_amount
 *     − yang sudah benar-benar dibayar, bukan angka baru.
 *
 * Kalau riwayat yang sudah tercatat MELEBIHI total jadwal teoritis (mis.
 * rate/tenor yang tersnapshot di loan tidak persis sama dengan yang
 * dipakai dulu), kelebihannya diabaikan (jadwal ditandai lunas semua) dan
 * dilaporkan lewat `warnings` — bukan dianggap gagal, supaya satu pinjaman
 * bermasalah tidak menghentikan perbaikan pinjaman lain, tapi tetap
 * terlihat oleh pengurus untuk ditinjau manual.
 *
 * Keterbatasan yang diketahui (di luar cakupan kelas ini): baris
 * loan_repayments hasil migrasi (kolom migrated_at terisi) tidak punya
 * schedule_allocations, jadi kalau salah satu baris riwayat LAMA itu
 * dibatalkan lewat menu Batalkan setelah backfill ini, jadwal tidak akan
 * otomatis terbuka kembali dengan benar — hanya relevan untuk pembatalan
 * transaksi lama hasil migrasi, bukan transaksi baru.
 */
class LoanScheduleRepairService
{
    public function __construct(private readonly LoanScheduleCalculator $calculator) {}

    /**
     * Pinjaman aktif yang loan_schedules-nya kosong — daftar yang
     * ditampilkan di halaman Perbaikan Jadwal Angsuran sebelum dieksekusi.
     *
     * @return Collection<int, Loan>
     */
    public function findBroken(): Collection
    {
        return Loan::query()
            ->where('status', 'dicairkan')
            ->with(['member', 'loanProduct', 'schedules'])
            ->get()
            ->filter(fn (Loan $loan) => $loan->schedules->count() === 0)
            ->values();
    }

    /**
     * Pratinjau (baca-saja, tidak menulis apa pun) — dipakai untuk
     * menampilkan "Outstanding setelah diperbaiki" & peringatan di halaman
     * sebelum pengurus menekan "Jalankan Perbaikan".
     *
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     new_outstanding: float,
     *     warnings: array<int, string>,
     * }
     */
    public function plan(Loan $loan): array
    {
        $product = $loan->loanProduct;
        $start = $loan->disbursed_at ?? $loan->created_at ?? now();
        $principal = (float) $loan->principal_amount;

        $rows = $loan->usesDailyTenor()
            ? $this->calculator->calculateDaily($principal, (int) $loan->tenor_days, (float) $loan->interest_rate_percentage, $product->calculation_method, $start)
            : $this->calculator->calculate($principal, (int) $loan->tenor_days, (float) $loan->interest_rate_percentage, $product->calculation_method, $start);

        $totalSchedulePrincipal = round(array_sum(array_column($rows, 'principal_amount')), 2);
        $totalScheduleInterest = round(array_sum(array_column($rows, 'interest_amount')), 2);

        $paid = LoanRepayment::query()
            ->where('loan_id', $loan->id)
            ->whereNull('cancelled_at')
            ->selectRaw('COALESCE(SUM(principal_portion),0) as p, COALESCE(SUM(interest_portion),0) as i')
            ->first();

        $remainingPrincipal = round((float) $paid->p, 2);
        $remainingInterest = round((float) $paid->i, 2);

        $warnings = [];
        if ($remainingPrincipal > $totalSchedulePrincipal + 0.01) {
            $warnings[] = 'Riwayat pokok terbayar (Rp '.number_format($remainingPrincipal, 0, ',', '.')
                .') melebihi total jadwal teoritis (Rp '.number_format($totalSchedulePrincipal, 0, ',', '.')
                .') — kelebihan diabaikan, jadwal ditandai lunas semua. Tinjau manual.';
        }
        if ($remainingInterest > $totalScheduleInterest + 0.01) {
            $warnings[] = 'Riwayat jasa terbayar (Rp '.number_format($remainingInterest, 0, ',', '.')
                .') melebihi total jadwal teoritis (Rp '.number_format($totalScheduleInterest, 0, ',', '.')
                .') — kelebihan diabaikan. Tinjau manual.';
        }

        $toInsert = [];

        foreach ($rows as $row) {
            $interestAmount = $row['interest_amount'];
            $principalAmount = $row['principal_amount'];

            $paidInterest = min($interestAmount, $remainingInterest);
            $remainingInterest = round($remainingInterest - $paidInterest, 2);

            $paidPrincipal = min($principalAmount, $remainingPrincipal);
            $remainingPrincipal = round($remainingPrincipal - $paidPrincipal, 2);

            $paidAmount = round($paidPrincipal + $paidInterest, 2);
            $totalAmount = round($principalAmount + $interestAmount, 2);

            $toInsert[] = [
                'loan_id' => $loan->id,
                'installment_number' => $row['installment_number'],
                'due_date' => $row['due_date']->toDateString(),
                'principal_amount' => $principalAmount,
                'interest_amount' => $interestAmount,
                'total_amount' => $totalAmount,
                'paid_principal_amount' => $paidPrincipal,
                'paid_interest_amount' => $paidInterest,
                'paid_amount' => $paidAmount,
                'status' => $paidAmount >= $totalAmount - 0.01 ? 'lunas' : ($paidAmount > 0 ? 'sebagian' : 'belum_bayar'),
            ];
        }

        $newOutstanding = round($totalSchedulePrincipal - array_sum(array_column($toInsert, 'paid_principal_amount')), 2);

        return [
            'rows' => $toInsert,
            'new_outstanding' => $newOutstanding,
            'warnings' => $warnings,
        ];
    }

    /**
     * Eksekusi nyata untuk satu pinjaman — insert baris hasil plan() dalam
     * satu transaksi DB. Aman diulang: kalau pinjaman ini SUDAH punya
     * jadwal (mis. sudah pernah diperbaiki, atau ternyata tidak pernah
     * kosong), findBroken() tidak akan menyertakannya lagi di scan
     * berikutnya, jadi tidak akan pernah menulis dobel — tapi caller tetap
     * disarankan memverifikasi $loan->schedules->count() === 0 sebelum
     * memanggil ini (lihat LoanScheduleRepairController::store()).
     *
     * @return array{rows: array<int, array<string, mixed>>, new_outstanding: float, warnings: array<int, string>}
     */
    public function repair(Loan $loan): array
    {
        $result = $this->plan($loan);

        DB::transaction(function () use ($loan, $result) {
            foreach ($result['rows'] as $row) {
                $loan->schedules()->create($row);
            }
        });

        return $result;
    }
}
