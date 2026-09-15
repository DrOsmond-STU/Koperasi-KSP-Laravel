<?php

namespace App\Services\Loans;

use App\Exceptions\Accounting\CashAccountException;
use App\Exceptions\Accounting\JournalPostingException;
use App\Exceptions\Loans\LoanApprovalException;
use App\Models\ChartOfAccount;
use App\Models\Loan;
use App\Models\LoanApproval;
use App\Models\User;
use App\Services\Accounting\CashAccountResolver;
use App\Services\Accounting\JournalEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Approval berjenjang pinjaman (PRD §7.3, §8; 06_TESTING.md AUTH-09/AUTH-12).
 * A loan reaches `required_approval_count` distinct approvers before it is
 * disbursed — never in one step, and never by the person who created it.
 */
class LoanApprovalService
{
    public function __construct(
        private readonly JournalEngine $journalEngine,
        private readonly LoanScheduleCalculator $scheduleCalculator,
        private readonly CashAccountResolver $cashAccounts,
        private readonly LoanBranchResolver $loanBranches,
    ) {}

    /**
     * $disbursedOn = tanggal persetujuan, yang sekaligus tanggal uang
     * benar-benar keluar. Diisi staf di layar persetujuan, bukan diambil
     * dari hari ini: koperasi mencatat pinjaman lama secara susulan (akad
     * Agustus baru masuk sistem sekarang), jadi menyamakan hari pencatatan
     * dengan hari pencairan membuat kas keluar dan seluruh jadwal
     * angsurannya meleset berbulan-bulan.
     *
     * Null hanya untuk pemanggil non-formulir; perilakunya jatuh ke
     * tanggalBerlaku().
     */
    public function approve(Loan $loan, User $approver, ?string $notes = null, ?string $disbursedOn = null): Loan
    {
        $this->guardCanDecide($loan, $approver);

        $tanggal = $disbursedOn === null ? null : Carbon::parse($disbursedOn)->startOfDay();

        return DB::transaction(function () use ($loan, $approver, $notes, $tanggal) {
            LoanApproval::query()->create([
                'loan_id' => $loan->id,
                'approved_by' => $approver->id,
                'decision' => 'setuju',
                'notes' => $notes,
                // Keputusan yang dicatat susulan tetap tercatat pada tanggal
                // keputusan yang sebenarnya, bukan tanggal penginputan.
                'decided_at' => $tanggal ?? now(),
            ]);

            $loan->refresh();

            if ($loan->isFullyApproved()) {
                $loan->update(['status' => 'disetujui']);
                $this->disburse($loan, $tanggal);
            }

            return $loan->fresh();
        });
    }

    public function reject(Loan $loan, User $approver, string $notes): Loan
    {
        $this->guardCanDecide($loan, $approver);

        return DB::transaction(function () use ($loan, $approver, $notes) {
            LoanApproval::query()->create([
                'loan_id' => $loan->id,
                'approved_by' => $approver->id,
                'decision' => 'tolak',
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $loan->update(['status' => 'ditolak']);

            return $loan->fresh();
        });
    }

    private function guardCanDecide(Loan $loan, User $approver): void
    {
        if ($loan->status !== 'diajukan') {
            throw LoanApprovalException::notPending($loan->status);
        }

        if ($loan->created_by === $approver->id) {
            throw LoanApprovalException::selfApproval();
        }

        if ($loan->hasBeenDecidedBy($approver->id)) {
            throw LoanApprovalException::alreadyDecidedByThisUser();
        }
    }

    /**
     * Tanggal berlakunya pinjaman: tanggal pengajuan bila staf memundurkannya
     * (pencatatan pinjaman lama), selain itu hari ini.
     *
     * Pengajuan biasa di loket diajukan dan disetujui pada hari yang sama,
     * sehingga keduanya bernilai sama dan perilakunya tidak berubah sedikit
     * pun. Yang berubah hanya pinjaman yang tanggal pengajuannya sengaja
     * dimundurkan: jadwal angsurannya dihitung mulai tanggal akad yang
     * sebenarnya, bukan mulai hari pencatatan — tanpa itu pinjaman lama akan
     * masuk sistem dengan jadwal yang seluruhnya belum jatuh tempo.
     */
    private function tanggalBerlaku(Loan $loan): Carbon
    {
        $diajukan = $loan->submitted_at;

        if ($diajukan === null || $diajukan->isToday() || $diajukan->isFuture()) {
            return Carbon::now();
        }

        return $diajukan->copy()->startOfDay();
    }

    /**
     * Jurnal pencairan (Dr Piutang Pinjaman, Cr Kas bersih + Cr Pendapatan
     * Provisi) dan pembentukan jadwal angsuran — dipanggil otomatis begitu
     * approval terakhir yang dibutuhkan masuk.
     *
     * CATATAN PENTING soal tanggal jurnal. Seluruh tanggal — entry_date
     * jurnal, disbursed_at, dan awal jadwal angsuran — mengikuti tanggal
     * pencairan yang diisi penyetuju di layar persetujuan.
     *
     * Sebelumnya entry_date dipatok ke hari ini dengan alasan memundurkan
     * jurnal bisa menyuntik kas keluar ke periode yang sudah ditutup.
     * Kekhawatirannya benar, tapi penyelesaiannya salah sasaran: memaksa
     * tanggal hari ini tidak mencegah apa pun, ia hanya memindahkan
     * kesalahan ke tempat yang lebih sulit dilihat — kas keluar tercatat di
     * bulan yang salah, dan neraca kedua bulan itu ikut salah. Yang benar
     * menjaga periode adalah AccountingPeriod: JournalEngine menolak posting
     * ke periode yang sudah ditutup, dan penolakan itu kini muncul sebagai
     * pesan yang bisa ditindaklanjuti (lihat blok try di bawah). Jadi
     * pencairan susulan masuk pada tanggal yang sebenarnya selama periodenya
     * masih terbuka, dan ditolak dengan jelas kalau sudah ditutup.
     */
    private function disburse(Loan $loan, ?Carbon $disbursedOn = null): void
    {
        $product = $loan->loanProduct;
        $principal = (float) $loan->principal_amount;
        $provisionFee = round($principal * (float) $product->provision_fee_percentage / 100, 2);
        $netCash = round($principal - $provisionFee, 2);
        $berlaku = $disbursedOn ?? $this->tanggalBerlaku($loan);

        $keterangan = "Pencairan pinjaman {$loan->loan_number}";

        // Jurnalnya sendiri sudah bertanggal pencairan yang sebenarnya, jadi
        // yang perlu dicatat di keterangan justru KAPAN ia diinput — supaya
        // pemeriksa tahu baris ini masuk susulan, bukan hari itu juga.
        if (! $berlaku->isToday()) {
            $keterangan .= ' (dicatat susulan pada '.now()->translatedFormat('d M Y').')';
        }

        // Salah konfigurasi bagan akun adalah kesalahan data yang bisa
        // diperbaiki admin — bukan bug yang pantas menjatuhkan halaman
        // persetujuan ke 500. Penentuan akun kasnya ikut masuk ke dalam
        // try: cabang yang belum diatur akun kasnya gagal di situ, sebelum
        // JournalEngine sempat dipanggil. Transaksi tetap dibatalkan
        // seutuhnya, jadi suara approval terakhir ikut mundur dan
        // persetujuan bisa diulang persis dari keadaan semula setelah
        // akunnya dibetulkan.
        try {
            $lines = [
                ['chart_of_account_id' => $product->coa_receivable_account_id, 'debit' => $principal, 'credit' => 0],
                ['chart_of_account_id' => $this->cashAccount($loan)->id, 'debit' => 0, 'credit' => $netCash],
            ];

            if ($provisionFee > 0) {
                $lines[] = ['chart_of_account_id' => $product->coa_provision_income_account_id, 'debit' => 0, 'credit' => $provisionFee];
            }

            $this->journalEngine->post([
                // Cabang unit yang menjalankan pinjaman, bukan cabang yang
                // kebetulan tersimpan di barisnya — lihat LoanBranchResolver.
                'branch_id' => $this->loanBranches->resolveOrFail($loan->branch_id),
                'entry_date' => $berlaku->toDateString(),
                'description' => $keterangan,
                'created_by' => $loan->created_by,
                'source' => $loan,
                'lines' => $lines,
            ]);
        } catch (CashAccountException|JournalPostingException $exception) {
            throw LoanApprovalException::disbursementPostingFailed($exception->getMessage());
        }

        // Satuan tenor (hari/bulan) di-snapshot ke pinjaman saat pengajuan
        // — lihat Loan::usesDailyTenor() / LoanService. Pinjaman anggota
        // (harian) dan piutang karyawan (bulanan, potong gaji) hidup
        // berdampingan selamanya, bukan "lama vs baru".
        $schedule = $loan->usesDailyTenor()
            ? $this->scheduleCalculator->calculateDaily(
                $principal,
                $loan->tenor_days,
                (float) $loan->interest_rate_percentage,
                $product->calculation_method,
                $berlaku,
            )
            : $this->scheduleCalculator->calculate(
                $principal,
                $loan->tenor_days,
                (float) $loan->interest_rate_percentage,
                $product->calculation_method,
                $berlaku,
            );

        foreach ($schedule as $row) {
            $loan->schedules()->create([
                'installment_number' => $row['installment_number'],
                'due_date' => $row['due_date'],
                'principal_amount' => $row['principal_amount'],
                'interest_amount' => $row['interest_amount'],
                'total_amount' => $row['total_amount'],
            ]);
        }

        $loan->update([
            'provision_fee_amount' => $provisionFee,
            'status' => 'dicairkan',
            'collectibility' => 'lancar',
            'disbursed_at' => $berlaku->toDateString(),
            // Disamakan dengan cabang jurnalnya. Tanpa ini baris pinjaman dan
            // jurnalnya menunjuk cabang berbeda, dan angsurannya nanti ikut
            // yang salah.
            'branch_id' => $this->loanBranches->resolveOrFail($loan->branch_id),
        ]);
    }

    /**
     * Akun kas yang dikredit saat pencairan: kas pencairan pinjaman yang
     * ditetapkan di Pengaturan → Kas Cabang.
     *
     * Bukan akun kas cabang seperti angsuran. Uang pencairan keluar dari kas
     * kecil unit, sementara angsurannya masuk lewat kas AO — keduanya memang
     * akun yang berbeda, dan menyamakannya justru membuat salah satunya
     * salah. `loans.branch_id` juga tidak bisa dipakai sebagai penunjuk unit
     * (temuan 25 Agu 2026: 142 pinjaman aktif seluruhnya tersimpan di cabang
     * root "KPPD Pusat"), jadi akunnya memang harus ditetapkan sendiri.
     *
     * Sebelumnya di sini tertulis konstanta '1101'. Di koperasi yang bagan
     * akunnya sendiri, '1101' bukan akun kas yang dipakai — malah dijadikan
     * akun header — sehingga pencairan selalu ditolak JournalEngine.
     */
    private function cashAccount(Loan $loan): ChartOfAccount
    {
        return $this->cashAccounts->forLoanDisbursement($loan->branch_id);
    }

    /**
     * Membatalkan pencairan pinjaman yang belum ada angsurannya sama sekali
     * (bukan restrukturisasi/pelunasan dipercepat — di luar cakupan ini).
     * Jadwal angsuran yang belum dibayar dihapus (murni derivasi hasil
     * perhitungan, tidak ada dampak kas independen) dan digantikan cukup
     * oleh jejak jurnal pembalik + kolom pembatalan pada baris pinjaman itu
     * sendiri, yang tetap ada (tidak pernah dihapus).
     */
    public function cancelDisbursement(Loan $loan, string $reason, int $cancelledBy): Loan
    {
        if ($loan->isCancelled()) {
            throw LoanApprovalException::alreadyCancelled();
        }

        if ($loan->status !== 'dicairkan') {
            throw LoanApprovalException::notDisbursed($loan->status);
        }

        if ($loan->schedules()->where('paid_amount', '>', 0)->exists()) {
            throw LoanApprovalException::hasPayments();
        }

        $disbursementEntry = $loan->disbursementJournalEntry;

        if ($disbursementEntry === null) {
            throw LoanApprovalException::noDisbursementJournal();
        }

        return DB::transaction(function () use ($loan, $reason, $cancelledBy, $disbursementEntry) {
            $reversalEntry = $this->journalEngine->reverse($disbursementEntry, $reason, $cancelledBy);

            $loan->schedules()->delete();

            $loan->update([
                'status' => 'dibatalkan',
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledBy,
                'cancellation_reason' => $reason,
                'reversal_journal_entry_id' => $reversalEntry->id,
            ]);

            return $loan->fresh();
        });
    }
}
