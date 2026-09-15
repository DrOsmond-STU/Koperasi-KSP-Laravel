<?php

namespace App\Services\Loans;

use App\Exceptions\Accounting\JournalPostingException;
use App\Exceptions\Loans\LoanApprovalException;
use App\Models\ChartOfAccount;
use App\Models\Loan;
use App\Models\LoanApproval;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\Accounting\JournalEngine;
use Illuminate\Support\Facades\DB;

/**
 * Approval berjenjang pinjaman (PRD §7.3, §8; 06_TESTING.md AUTH-09/AUTH-12).
 * A loan reaches `required_approval_count` distinct approvers before it is
 * disbursed — never in one step, and never by the person who created it.
 */
class LoanApprovalService
{
    private const DEFAULT_CASH_ACCOUNT_CODE = '1101';

    public function __construct(
        private readonly JournalEngine $journalEngine,
        private readonly LoanScheduleCalculator $scheduleCalculator,
    ) {}

    public function approve(Loan $loan, User $approver, ?string $notes = null): Loan
    {
        $this->guardCanDecide($loan, $approver);

        return DB::transaction(function () use ($loan, $approver, $notes) {
            LoanApproval::query()->create([
                'loan_id' => $loan->id,
                'approved_by' => $approver->id,
                'decision' => 'setuju',
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $loan->refresh();

            if ($loan->isFullyApproved()) {
                $loan->update(['status' => 'disetujui']);
                $this->disburse($loan);
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
     * Jurnal pencairan (Dr Piutang Pinjaman, Cr Kas bersih + Cr Pendapatan
     * Provisi) dan pembentukan jadwal angsuran — dipanggil otomatis begitu
     * approval terakhir yang dibutuhkan masuk.
     */
    private function disburse(Loan $loan): void
    {
        $product = $loan->loanProduct;
        $principal = (float) $loan->principal_amount;
        $provisionFee = round($principal * (float) $product->provision_fee_percentage / 100, 2);
        $netCash = round($principal - $provisionFee, 2);

        $lines = [
            ['chart_of_account_id' => $product->coa_receivable_account_id, 'debit' => $principal, 'credit' => 0],
            ['chart_of_account_id' => $this->cashAccountFor($product)->id, 'debit' => 0, 'credit' => $netCash],
        ];

        if ($provisionFee > 0) {
            $lines[] = ['chart_of_account_id' => $product->coa_provision_income_account_id, 'debit' => 0, 'credit' => $provisionFee];
        }

        // Salah konfigurasi bagan akun (akun kas dijadikan header, akun
        // piutang/provisi produk kosong, periode sudah ditutup) adalah
        // kesalahan data yang bisa diperbaiki admin — bukan bug yang pantas
        // menjatuhkan halaman persetujuan ke 500. Transaksi tetap dibatalkan
        // seutuhnya, jadi suara approval terakhir ikut mundur dan persetujuan
        // bisa diulang persis dari keadaan semula setelah akunnya dibetulkan.
        try {
            $this->journalEngine->post([
                'branch_id' => $loan->branch_id,
                'entry_date' => now()->toDateString(),
                'description' => "Pencairan pinjaman {$loan->loan_number}",
                'created_by' => $loan->created_by,
                'source' => $loan,
                'lines' => $lines,
            ]);
        } catch (JournalPostingException $exception) {
            throw LoanApprovalException::disbursementPostingFailed($exception->getMessage());
        }

        $schedule = $this->scheduleCalculator->calculate(
            $principal,
            $loan->tenor_months,
            (float) $loan->interest_rate_percentage,
            $product->calculation_method,
            now(),
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
            'disbursed_at' => now()->toDateString(),
        ]);
    }

    /**
     * Akun kas yang dikredit saat pencairan: milik produk pinjaman kalau
     * ditetapkan (koperasi dengan bagan akun sendiri mengarahkan tiap produk
     * ke akun kas unitnya — KSP, USP, UPF), selain itu jatuh ke akun kas
     * bawaan kode 1101 seperti perilaku lama.
     */
    private function cashAccountFor(LoanProduct $product): ChartOfAccount
    {
        if ($product->coa_cash_account_id !== null) {
            $account = $product->cashAccount;

            if ($account === null) {
                throw LoanApprovalException::disbursementPostingFailed(
                    "Akun kas yang ditetapkan pada produk pinjaman \"{$product->name}\" tidak ada di Bagan Akun."
                );
            }

            return $account;
        }

        $account = ChartOfAccount::query()->where('code', self::DEFAULT_CASH_ACCOUNT_CODE)->first();

        if ($account === null) {
            throw LoanApprovalException::disbursementPostingFailed(
                'Akun kas bawaan '.self::DEFAULT_CASH_ACCOUNT_CODE.' tidak ada di Bagan Akun, dan produk pinjaman ini belum menetapkan akun kas sendiri.'
            );
        }

        return $account;
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
