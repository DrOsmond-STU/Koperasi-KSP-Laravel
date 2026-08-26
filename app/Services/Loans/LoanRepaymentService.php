<?php

namespace App\Services\Loans;

use App\Exceptions\Loans\LoanRepaymentException;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Services\Accounting\JournalEngine;
use Illuminate\Support\Facades\DB;

/**
 * The loan-repayment engine that never existed before (loan_schedules.
 * paid_amount was always 0). previewAllocation() walks unpaid schedules
 * oldest-first, interest-first within each row (paid_interest_amount /
 * paid_principal_amount are always kept in sync with paid_amount so a
 * lump sum spanning several partial-paid installments never has to
 * reverse-derive the split). recordPayment() persists exactly what
 * previewAllocation() computed — the two can never drift apart because
 * recordPayment() calls it internally rather than reimplementing the walk.
 */
class LoanRepaymentService
{
    private const DEFAULT_CASH_ACCOUNT_CODE = '1101';

    public function __construct(
        private readonly JournalEngine $journalEngine,
        private readonly LoanScheduleCalculator $scheduleCalculator,
    ) {}

    /**
     * @return array{
     *     allocations: array<int, array{schedule_id: int, installment_number: int, allocated: float, principal_share: float, interest_share: float}>,
     *     total_principal: float,
     *     total_interest: float,
     *     total_allocated: float,
     *     outstanding_before: float,
     *     remaining_unallocated: float,
     * }
     */
    public function previewAllocation(Loan $loan, float $amount): array
    {
        $remaining = round($amount, 2);
        $allocations = [];
        $totalPrincipal = 0.0;
        $totalInterest = 0.0;

        $schedules = $loan->schedules()->where('status', '!=', 'lunas')->orderBy('installment_number')->get();
        $outstandingBefore = round((float) $schedules->sum(
            fn (LoanSchedule $s) => (float) $s->total_amount - (float) $s->paid_amount,
        ), 2);

        foreach ($schedules as $schedule) {
            if ($remaining <= 0) {
                break;
            }

            $remainingInterest = round((float) $schedule->interest_amount - (float) $schedule->paid_interest_amount, 2);
            $remainingPrincipal = round((float) $schedule->principal_amount - (float) $schedule->paid_principal_amount, 2);
            $remainingOnRow = round($remainingInterest + $remainingPrincipal, 2);

            $allocatedToRow = min($remainingOnRow, $remaining);

            if ($allocatedToRow <= 0) {
                continue;
            }

            $interestShare = min($allocatedToRow, $remainingInterest);
            $principalShare = round($allocatedToRow - $interestShare, 2);

            $allocations[] = [
                'schedule_id' => $schedule->id,
                'installment_number' => $schedule->installment_number,
                'allocated' => $allocatedToRow,
                'principal_share' => $principalShare,
                'interest_share' => $interestShare,
            ];

            $totalPrincipal = round($totalPrincipal + $principalShare, 2);
            $totalInterest = round($totalInterest + $interestShare, 2);
            $remaining = round($remaining - $allocatedToRow, 2);
        }

        return [
            'allocations' => $allocations,
            'total_principal' => $totalPrincipal,
            'total_interest' => $totalInterest,
            'total_allocated' => round($totalPrincipal + $totalInterest, 2),
            'outstanding_before' => $outstandingBefore,
            'remaining_unallocated' => $remaining,
        ];
    }

    /**
     * $date = tanggal pembayaran SEBENARNYA (mis. staf menyusulkan angsuran
     * lama yang belum tercatat) — default ke hari ini kalau tidak diisi.
     * Dipakai untuk loan_repayments.paid_at DAN entry_date jurnal, supaya
     * keduanya selalu konsisten (mirror pola RetributionService::record()).
     */
    public function recordPayment(
        Loan $loan,
        float $amount,
        int $createdBy,
        ?string $description = null,
        ?string $idempotencyKey = null,
        ?\DateTimeInterface $date = null,
        ?int $cashAccountId = null,
    ): LoanRepayment {
        if ($loan->status !== 'dicairkan') {
            throw LoanRepaymentException::notActive($loan->status);
        }

        $plan = $this->previewAllocation($loan, $amount);

        if ($plan['remaining_unallocated'] > 0) {
            throw LoanRepaymentException::overpayment(
                number_format($amount, 2, '.', ''),
                number_format($plan['outstanding_before'], 2, '.', ''),
            );
        }

        $entryDate = $date ?? now();

        return DB::transaction(function () use ($loan, $amount, $createdBy, $description, $idempotencyKey, $plan, $entryDate, $cashAccountId) {
            foreach ($plan['allocations'] as $allocation) {
                $schedule = LoanSchedule::query()->lockForUpdate()->findOrFail($allocation['schedule_id']);

                $newPaidPrincipal = round((float) $schedule->paid_principal_amount + $allocation['principal_share'], 2);
                $newPaidInterest = round((float) $schedule->paid_interest_amount + $allocation['interest_share'], 2);
                $newPaidAmount = round($newPaidPrincipal + $newPaidInterest, 2);

                $schedule->update([
                    'paid_principal_amount' => $newPaidPrincipal,
                    'paid_interest_amount' => $newPaidInterest,
                    'paid_amount' => $newPaidAmount,
                    'status' => $newPaidAmount >= (float) $schedule->total_amount ? 'lunas' : 'sebagian',
                ]);
            }

            $product = $loan->loanProduct;
            $lines = [
                ['chart_of_account_id' => $this->cashAccount($loan, $cashAccountId)->id, 'debit' => $amount, 'credit' => 0],
            ];

            if ($plan['total_principal'] > 0) {
                $lines[] = ['chart_of_account_id' => $product->coa_receivable_account_id, 'debit' => 0, 'credit' => $plan['total_principal']];
            }

            if ($plan['total_interest'] > 0) {
                $lines[] = ['chart_of_account_id' => $product->coa_interest_income_account_id, 'debit' => 0, 'credit' => $plan['total_interest']];
            }

            // Created before the journal so it can be the entry's polymorphic
            // `source` (Loan already claims that slot for its disbursement
            // journal — see Loan::disbursementJournalEntry()).
            $repayment = LoanRepayment::query()->create([
                'branch_id' => $loan->branch_id,
                'loan_id' => $loan->id,
                'amount' => $amount,
                'principal_portion' => $plan['total_principal'],
                'interest_portion' => $plan['total_interest'],
                'balance_after' => round($plan['outstanding_before'] - $amount, 2),
                'paid_at' => $entryDate->format('Y-m-d'),
                'created_by' => $createdBy,
                'description' => $description,
            ]);

            $entry = $this->journalEngine->post([
                'branch_id' => $loan->branch_id,
                'entry_date' => $entryDate->format('Y-m-d'),
                'description' => $description ?? "Pembayaran angsuran {$loan->loan_number}",
                'created_by' => $createdBy,
                'source' => $repayment,
                'idempotency_key' => $idempotencyKey,
                'lines' => $lines,
            ]);

            $repayment->update(['journal_entry_id' => $entry->id]);

            if (! $loan->schedules()->where('status', '!=', 'lunas')->exists()) {
                $loan->update(['status' => 'lunas']);
            }

            return $repayment->fresh();
        });
    }

    /**
     * Pokok + Jasa SATU periode normal — "pinjaman dibagi lama pinjaman +
     * % jasa" (instruksi KPPD Depok, 26 Agu 2026), dihitung ulang dari
     * principal_amount/tenor_days/interest_rate_percentage yang di-snapshot
     * di Loan sendiri saat pengajuan. SENGAJA tidak melihat loan_schedules
     * sama sekali — banyak anggota di KPPD Depok nunggak, dan sisa tagihan
     * beberapa pinjaman lama sudah "menggumpal" jadi satu baris jadwal
     * dengan jasa jauh lebih besar dari angsuran normal (laporan staf 25
     * Agu 2026: pinjaman 117-0151-01059). Nilai ini hanya SARAN default
     * untuk field "Angsuran Pokok"/"Jasa" di form Catat Angsuran — staf
     * tetap bisa mengeditnya (lihat recordManualPayment()).
     *
     * @return array{principal: float, interest: float}
     */
    public function normalInstallment(Loan $loan): array
    {
        $product = $loan->loanProduct;
        $rows = $loan->usesDailyTenor()
            ? $this->scheduleCalculator->calculateDaily(
                (float) $loan->principal_amount,
                $loan->tenor_days,
                (float) $loan->interest_rate_percentage,
                $product->calculation_method,
                now(),
            )
            : $this->scheduleCalculator->calculate(
                (float) $loan->principal_amount,
                $loan->tenor_days,
                (float) $loan->interest_rate_percentage,
                $product->calculation_method,
                now(),
            );

        $first = $rows[0];

        return ['principal' => $first['principal_amount'], 'interest' => $first['interest_amount']];
    }

    /**
     * Sisa pokok pinjaman saat ini — sum(schedules.principal_amount) -
     * sum(schedules.paid_principal_amount), sama persis dengan "Sisa Pokok"
     * pada cetakan Laporan Pinjaman Anggota (prints/loans/list.blade.php).
     * Dipakai form Catat Angsuran untuk menampilkan Saldo Outstanding
     * (read-only) begitu Pinjaman dipilih — laporan staf 26 Agu 2026: staf
     * perlu lihat sisa pinjaman anggota SEBELUM mengetik Nominal Bayar.
     * Butuh relasi `schedules` sudah di-eager-load (lihat
     * LoanRepaymentController::create()) supaya tidak N+1 per baris dropdown.
     */
    public function outstandingPrincipal(Loan $loan): float
    {
        return round(
            (float) $loan->schedules->sum('principal_amount') - (float) $loan->schedules->sum('paid_principal_amount'),
            2,
        );
    }

    /**
     * Baris jurnal yang AKAN diposting kalau angsuran ini dikonfirmasi —
     * dipakai staf/angsuran-preview.blade.php untuk menampilkan akun COA di
     * bawah tabel Komponen, sebelum staf menekan "Konfirmasi & Simpan"
     * (laporan staf 26 Agu 2026). Murni pratinjau baca-saja — TIDAK
     * memposting apa pun. Susunan baris SENGAJA disalin persis dari
     * recordManualPayment() (kas didebit sebesar total, lalu masing-masing
     * komponen Pokok/Jasa/Denda yang > 0 dikredit ke akun produknya) supaya
     * pratinjau ini tidak pernah menyesatkan dibanding jurnal yang benar-
     * benar diposting nanti. Akun bisa null kalau produk pinjamannya belum
     * dikonfigurasi lengkap (mis. coa_penalty_receivable_account_id kosong)
     * — view menampilkannya sebagai peringatan, bukan bikin preview gagal.
     *
     * @return array<int, array{account: ?ChartOfAccount, debit: float, credit: float}>
     */
    public function previewJournalLines(
        Loan $loan,
        ChartOfAccount $cashAccount,
        float $principalPortion,
        float $interestPortion,
        float $penaltyPortion,
    ): array {
        $product = $loan->loanProduct;
        $totalAmount = round($principalPortion + $interestPortion + $penaltyPortion, 2);

        $lines = [
            ['account' => $cashAccount, 'debit' => $totalAmount, 'credit' => 0.0],
        ];

        if ($principalPortion > 0) {
            $lines[] = ['account' => $product->receivableAccount, 'debit' => 0.0, 'credit' => $principalPortion];
        }

        if ($interestPortion > 0) {
            $lines[] = ['account' => $product->interestIncomeAccount, 'debit' => 0.0, 'credit' => $interestPortion];
        }

        if ($penaltyPortion > 0) {
            $lines[] = ['account' => $product->penaltyReceivableAccount, 'debit' => 0.0, 'credit' => $penaltyPortion];
        }

        return $lines;
    }

    /**
     * Catat Angsuran (staf/teller) — BEDA dari recordPayment(): staf
     * menentukan sendiri pembagian Pokok/Jasa/Denda (lihat
     * normalInstallment() untuk saran default di form), BUKAN dihitung
     * otomatis dari sisa baris loan_schedules seperti recordPayment()
     * (dipakai LoanRepaymentGatewayService untuk pembayaran mandiri via
     * Xendit — jalur itu TIDAK berubah). Instruksi KPPD Depok, 26 Agu 2026:
     * banyak anggota nunggak, tapi perhitungan angsuran harus tetap normal
     * (pokok = pinjaman ÷ lama pinjaman, jasa = tarif normal) — jangan
     * mengejar tunggakan atau baris jadwal yang sudah menggumpal.
     *
     * loan_schedules TETAP diperbarui — Pokok+Jasa yang diinput staf
     * dikonsumsi dari baris belum lunas (tertua dulu, interest-first per
     * baris, sama seperti recordPayment()) supaya status jadwal & cetakan
     * tetap berjalan. Tapi jurnal & loan_repayments.principal_portion/
     * interest_portion/penalty_portion memakai ANGKA STAF, bukan hasil
     * hitung baris jadwal — dua hal ini sengaja dipisah: baris jadwal cuma
     * penanda "berapa lagi yang harus dibayar", bukan penentu pembagian
     * akuntansi pembayaran ini.
     */
    public function recordManualPayment(
        Loan $loan,
        float $principalPortion,
        float $interestPortion,
        float $penaltyPortion,
        int $createdBy,
        ?string $description = null,
        ?string $idempotencyKey = null,
        ?\DateTimeInterface $date = null,
        ?int $cashAccountId = null,
    ): LoanRepayment {
        if ($loan->status !== 'dicairkan') {
            throw LoanRepaymentException::notActive($loan->status);
        }

        $principalPortion = round($principalPortion, 2);
        $interestPortion = round($interestPortion, 2);
        $penaltyPortion = round($penaltyPortion, 2);
        $totalAmount = round($principalPortion + $interestPortion + $penaltyPortion, 2);

        if ($totalAmount <= 0) {
            throw LoanRepaymentException::zeroPayment();
        }

        $product = $loan->loanProduct;

        if ($penaltyPortion > 0 && $product->coa_penalty_receivable_account_id === null) {
            throw LoanRepaymentException::missingPenaltyAccount();
        }

        $scheduleConsumption = round($principalPortion + $interestPortion, 2);
        $plan = $this->previewAllocation($loan, $scheduleConsumption);

        if ($plan['remaining_unallocated'] > 0) {
            throw LoanRepaymentException::overpayment(
                number_format($scheduleConsumption, 2, '.', ''),
                number_format($plan['outstanding_before'], 2, '.', ''),
            );
        }

        $entryDate = $date ?? now();

        return DB::transaction(function () use (
            $loan, $product, $principalPortion, $interestPortion, $penaltyPortion, $totalAmount,
            $scheduleConsumption, $createdBy, $description, $idempotencyKey, $plan, $entryDate, $cashAccountId,
        ) {
            // Direkam apa adanya (bukan cuma dipakai lalu dibuang) — ini
            // satu-satunya jejak "baris jadwal mana disentuh berapa" milik
            // pembayaran INI, dipakai reverseRepayment() supaya pembatalan
            // bisa mengembalikan paid_principal_amount/paid_interest_amount
            // TEPAT ke sebelum pembayaran ini, terlepas dari urutan
            // pembatalan (laporan staf 26 Agu 2026).
            $scheduleAllocations = [];

            foreach ($plan['allocations'] as $allocation) {
                $schedule = LoanSchedule::query()->lockForUpdate()->findOrFail($allocation['schedule_id']);

                $newPaidPrincipal = round((float) $schedule->paid_principal_amount + $allocation['principal_share'], 2);
                $newPaidInterest = round((float) $schedule->paid_interest_amount + $allocation['interest_share'], 2);
                $newPaidAmount = round($newPaidPrincipal + $newPaidInterest, 2);

                $schedule->update([
                    'paid_principal_amount' => $newPaidPrincipal,
                    'paid_interest_amount' => $newPaidInterest,
                    'paid_amount' => $newPaidAmount,
                    'status' => $newPaidAmount >= (float) $schedule->total_amount ? 'lunas' : 'sebagian',
                ]);

                $scheduleAllocations[] = [
                    'schedule_id' => $schedule->id,
                    'principal_share' => $allocation['principal_share'],
                    'interest_share' => $allocation['interest_share'],
                ];
            }

            $lines = [
                ['chart_of_account_id' => $this->cashAccount($loan, $cashAccountId)->id, 'debit' => $totalAmount, 'credit' => 0],
            ];

            if ($principalPortion > 0) {
                $lines[] = ['chart_of_account_id' => $product->coa_receivable_account_id, 'debit' => 0, 'credit' => $principalPortion];
            }

            if ($interestPortion > 0) {
                $lines[] = ['chart_of_account_id' => $product->coa_interest_income_account_id, 'debit' => 0, 'credit' => $interestPortion];
            }

            if ($penaltyPortion > 0) {
                $lines[] = ['chart_of_account_id' => $product->coa_penalty_receivable_account_id, 'debit' => 0, 'credit' => $penaltyPortion];
            }

            $repayment = LoanRepayment::query()->create([
                'branch_id' => $loan->branch_id,
                'loan_id' => $loan->id,
                'amount' => $totalAmount,
                'principal_portion' => $principalPortion,
                'interest_portion' => $interestPortion,
                'penalty_portion' => $penaltyPortion,
                'schedule_allocations' => $scheduleAllocations,
                'balance_after' => round($plan['outstanding_before'] - $scheduleConsumption, 2),
                'paid_at' => $entryDate->format('Y-m-d'),
                'created_by' => $createdBy,
                'description' => $description,
            ]);

            $entry = $this->journalEngine->post([
                'branch_id' => $loan->branch_id,
                'entry_date' => $entryDate->format('Y-m-d'),
                'description' => $description ?? "Pembayaran angsuran {$loan->loan_number}",
                'created_by' => $createdBy,
                'source' => $repayment,
                'idempotency_key' => $idempotencyKey,
                'lines' => $lines,
            ]);

            $repayment->update(['journal_entry_id' => $entry->id]);

            if (! $loan->schedules()->where('status', '!=', 'lunas')->exists()) {
                $loan->update(['status' => 'lunas']);
            }

            return $repayment->fresh();
        });
    }

    /**
     * Pembatalan (void) angsuran yang sudah dicatat — laporan staf 26 Agu
     * 2026: "kalau ada edit atau delete angsuran, jurnal COA harus di-
     * update juga". Baris `loan_repayments` asli TIDAK PERNAH diubah/
     * dihapus, hanya ditandai dibatalkan (sama filosofi dengan
     * SavingsService::reverseTransaction()/RetributionService::
     * reverseTransaction() — JournalEngine append-only, LED-06). Untuk
     * MENGEDIT angsuran yang salah: batalkan dulu lewat method ini, lalu
     * catat ulang lewat recordManualPayment() (form Catat Angsuran biasa)
     * dengan angka yang benar — bukan mengubah baris yang sudah diposting.
     *
     * Membalik DUA hal:
     *  1. Jurnal — via JournalEngine::reverse() (debit/kredit ditukar,
     *     entry asli tidak disentuh, reversal_of_entry_id tertaut).
     *  2. loan_schedules — paid_principal_amount/paid_interest_amount/
     *     paid_amount dikurangi PERSIS sebesar schedule_allocations yang
     *     direkam recordManualPayment() (lihat kolom itu), status baris
     *     dihitung ulang (bisa turun dari 'lunas'/'sebagian' balik ke
     *     'belum_bayar'). Kalau Loan sempat ditandai 'lunas' gara-gara
     *     angsuran ini, dibuka lagi jadi 'dicairkan' — aman dilakukan
     *     tanpa syarat tambahan: begitu SEMUA baris jadwal lunas,
     *     recordManualPayment() menolak pembayaran susulan apa pun
     *     (overpayment guard), jadi 'lunas' hanya bisa dipicu oleh SATU
     *     angsuran — membatalkan angsuran lain yang lebih tua sekalipun
     *     pasti membuka kembali minimal satu baris jadwal.
     */
    public function reverseRepayment(LoanRepayment $repayment, string $reason, int $cancelledBy): LoanRepayment
    {
        if ($repayment->isCancelled()) {
            throw LoanRepaymentException::alreadyCancelled();
        }

        return DB::transaction(function () use ($repayment, $reason, $cancelledBy) {
            $loan = $repayment->loan;

            foreach ((array) $repayment->schedule_allocations as $allocation) {
                $schedule = LoanSchedule::query()->lockForUpdate()->find($allocation['schedule_id']);

                if ($schedule === null) {
                    continue;
                }

                $newPaidPrincipal = round((float) $schedule->paid_principal_amount - (float) $allocation['principal_share'], 2);
                $newPaidInterest = round((float) $schedule->paid_interest_amount - (float) $allocation['interest_share'], 2);
                $newPaidAmount = round($newPaidPrincipal + $newPaidInterest, 2);

                $schedule->update([
                    'paid_principal_amount' => max(0.0, $newPaidPrincipal),
                    'paid_interest_amount' => max(0.0, $newPaidInterest),
                    'paid_amount' => max(0.0, $newPaidAmount),
                    'status' => match (true) {
                        $newPaidAmount <= 0 => 'belum_bayar',
                        $newPaidAmount >= (float) $schedule->total_amount => 'lunas',
                        default => 'sebagian',
                    },
                ]);
            }

            $reversalEntry = $this->journalEngine->reverse($repayment->journalEntry, $reason, $cancelledBy);

            $repayment->update([
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledBy,
                'cancellation_reason' => $reason,
                'reversal_journal_entry_id' => $reversalEntry->id,
            ]);

            if ($loan->status === 'lunas' && $loan->schedules()->where('status', '!=', 'lunas')->exists()) {
                $loan->update(['status' => 'dicairkan']);
            }

            return $repayment->fresh();
        });
    }

    /**
     * $cashAccountId datang dari pilihan staf di form Catat Angsuran (select
     * "Akun Kas Penerima", lihat LoanRepaymentController) — kalau diisi,
     * SELALU menang, apa pun cabang pinjamannya. Ini yang membuat akun kas
     * lawan bisa diedit per-transaksi (laporan staf 25 Agu 2026: akun kas
     * lawan angsuran jangan di-hardcode ke satu akun tetap).
     *
     * Kalau tidak diisi (mis. dipanggil dari webhook Xendit —
     * LoanRepaymentGatewayService, yang belum ada UI pemilihan akun), jatuh
     * ke akun kas cabang si pinjaman kalau sudah dikonfigurasi (Branch::
     * cashAccount, lihat admin.pengaturan.kas-cabang), lalu fallback ke
     * `1101` untuk cabang yang belum dikonfigurasi sama sekali.
     */
    private function cashAccount(Loan $loan, ?int $cashAccountId): ChartOfAccount
    {
        if ($cashAccountId !== null) {
            return ChartOfAccount::query()->findOrFail($cashAccountId);
        }

        return $loan->branch?->cashAccount
            ?? ChartOfAccount::query()->where('code', self::DEFAULT_CASH_ACCOUNT_CODE)->firstOrFail();
    }

    /**
     * Default yang ditawarkan di dropdown "Akun Kas Penerima" pada form
     * Catat Angsuran SEBELUM staf memilih sendiri — kas cabang "Unit Simpan
     * Pinjam (USP)", bukan akun kas cabang si pinjaman sendiri (`branch_id`
     * pada baris `loans` untuk pinjaman anggota yang sudah berjalan masih
     * banyak tersimpan "KPPD Pusat", peninggalan data lama — lihat temuan
     * 25 Agu 2026: 142 pinjaman aktif semuanya branch_id itu — padahal
     * secara bisnis produk pinjaman anggota koperasi ini semuanya USP).
     * Diekspos publik (bukan konstanta kode/nama akun statis di controller
     * atau view) supaya SELALU mengikuti pemetaan Kas per Cabang yang live
     * di admin.pengaturan.kas-cabang, dan dicari lewat NAMA cabang (bukan
     * id/code tetap) — sama alasannya dengan
     * RetributionController::defaultUpfBranchId(): cabang didaftarkan
     * manual oleh pengurus lewat menu Master Cabang, bukan seeder.
     */
    public function defaultCashAccount(): ?ChartOfAccount
    {
        return Branch::query()->where('name', 'LIKE', '%USP%')->first()?->cashAccount;
    }
}
