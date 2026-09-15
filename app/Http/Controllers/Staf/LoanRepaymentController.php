<?php

namespace App\Http\Controllers\Staf;

use App\Exceptions\Loans\LoanRepaymentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelLoanRepaymentRequest;
use App\Http\Requests\StafLoanRepaymentRequest;
use App\Models\ChartOfAccount;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Services\Loans\LoanRepaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Catat Angsuran (teller) — pembayaran tunai yang diterima langsung di
 * loket, dicatat seketika lewat LoanRepaymentService::recordManualPayment()
 * — staf yang menentukan pembagian Pokok/Jasa/Denda (instruksi KPPD Depok,
 * 26 Agu 2026), bukan dihitung otomatis dari sisa jadwal. Berbeda dari
 * Anggota\LoanRepaymentController: itu Bayar Angsuran Mandiri lewat Portal
 * (gateway Xendit, nunggu webhook, tetap lewat recordPayment() otomatis —
 * TIDAK berubah); ini tidak lewat gateway sama sekali — sejajar
 * Staf\TellerController untuk simpanan (preview lalu konfirmasi, bukan
 * langsung posting dari form pertama).
 */
class LoanRepaymentController extends Controller
{
    public function __construct(private readonly LoanRepaymentService $repayments) {}

    public function create(): View
    {
        $this->authorize('pinjaman.create');

        $loans = Loan::query()
            ->where('status', 'dicairkan')
            ->with(['member', 'loanProduct', 'schedules'])
            ->orderBy('loan_number')
            ->get();

        return view('staf.angsuran', [
            'loans' => $loans,
            'recentRepayments' => LoanRepayment::query()
                ->whereDate('created_at', now()->toDateString())
                ->with(['loan.member'])
                ->latest()
                ->limit(20)
                ->get(),
            'preselectedLoanId' => request()->integer('loan_id') ?: null,
            // Daftar akun kas yang bisa dipilih staf (postable + "kas" di
            // nama, sama pola dengan BranchCashSettingsController::index())
            // + default dinamis (kas cabang USP) — lihat
            // LoanRepaymentService::defaultCashAccount().
            'cashAccounts' => ChartOfAccount::query()
                ->where('is_postable', true)
                ->where('name', 'like', '%kas%')
                ->orderBy('code')
                ->get(),
            'defaultCashAccountId' => $this->repayments->defaultCashAccount()?->id,
            // Saran default Pokok/Jasa NORMAL per pinjaman (pinjaman ÷ lama
            // pinjaman + % jasa, TIDAK melihat tunggakan/baris jadwal yang
            // menggumpal — lihat LoanRepaymentService::normalInstallment()),
            // dipakai JS di form untuk mengisi field Pokok/Jasa begitu staf
            // memilih Pinjaman.
            'normalInstallments' => $loans->mapWithKeys(
                fn (Loan $loan) => [$loan->id => $this->repayments->normalInstallment($loan)],
            ),
            // Saldo outstanding (sisa pokok) per pinjaman, ditampilkan
            // read-only di form begitu staf memilih Pinjaman — lihat
            // LoanRepaymentService::outstandingPrincipal().
            'outstandingBalances' => $loans->mapWithKeys(
                fn (Loan $loan) => [$loan->id => $this->repayments->outstandingPrincipal($loan)],
            ),
        ]);
    }

    public function preview(StafLoanRepaymentRequest $request): View|RedirectResponse
    {
        $loan = Loan::query()->with(['member', 'loanProduct'])->findOrFail($request->validated('loan_id'));
        $principalPortion = (float) $request->validated('principal_portion');
        $interestPortion = (float) $request->validated('interest_portion');
        $penaltyPortion = (float) $request->validated('penalty_portion');

        $plan = $this->repayments->previewAllocation($loan, $principalPortion + $interestPortion);

        if ($plan['remaining_unallocated'] > 0) {
            return back()->withErrors([
                'principal_portion' => 'Angsuran Pokok + Jasa melebihi total tunggakan saat ini (Rp '.number_format($plan['outstanding_before'], 0, ',', '.').').',
            ])->withInput();
        }

        $cashAccount = ChartOfAccount::query()->find($request->validated('cash_account_id'));

        return view('staf.angsuran-preview', [
            'loan' => $loan,
            'principalPortion' => $principalPortion,
            'interestPortion' => $interestPortion,
            'penaltyPortion' => $penaltyPortion,
            'totalAmount' => round($principalPortion + $interestPortion + $penaltyPortion, 2),
            'description' => $request->validated('description'),
            'paidAt' => $request->validated('paid_at') ?: now()->toDateString(),
            'outstandingBefore' => $plan['outstanding_before'],
            'cashAccountId' => $request->validated('cash_account_id'),
            'cashAccount' => $cashAccount,
            // Akun COA yang akan didebit/dikredit kalau dikonfirmasi — lihat
            // LoanRepaymentService::previewJournalLines() (laporan staf 26
            // Agu 2026: tampilkan jurnal di bawah tabel Komponen).
            'journalLines' => $cashAccount
                ? $this->repayments->previewJournalLines(
                    $loan,
                    $cashAccount,
                    $principalPortion,
                    $interestPortion,
                    $penaltyPortion,
                )
                : [],
        ]);
    }

    public function store(StafLoanRepaymentRequest $request): RedirectResponse
    {
        $loan = Loan::query()->findOrFail($request->validated('loan_id'));
        $idempotencyKey = $request->input('idempotency_key') ?: (string) Str::uuid();
        $paidAt = $request->validated('paid_at');

        try {
            $repayment = $this->repayments->recordManualPayment(
                $loan,
                (float) $request->validated('principal_portion'),
                (float) $request->validated('interest_portion'),
                (float) $request->validated('penalty_portion'),
                $request->user()->id,
                $request->validated('description'),
                $idempotencyKey,
                $paidAt ? Carbon::parse($paidAt) : null,
                (int) $request->validated('cash_account_id'),
            );
        } catch (LoanRepaymentException $exception) {
            return back()->withErrors(['principal_portion' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('staf.angsuran.create')
            ->with('status', "Angsuran {$loan->loan_number} Rp ".number_format($repayment->amount, 0, ',', '.').' berhasil dicatat.');
    }

    /**
     * Batalkan angsuran yang salah catat — lihat LoanRepaymentService::
     * reverseRepayment() untuk apa yang dibalik (jurnal + loan_schedules).
     * Untuk MENGEDIT: batalkan dulu lewat sini, lalu catat ulang lewat
     * form Catat Angsuran biasa dengan angka yang benar.
     */
    public function cancel(CancelLoanRepaymentRequest $request, LoanRepayment $repayment): RedirectResponse
    {
        abort_unless($repayment->canBeCancelledBy($request->user()), 403, 'Anda hanya bisa membatalkan angsuran yang Anda catat sendiri.');

        try {
            $this->repayments->reverseRepayment($repayment, $request->validated('reason'), $request->user()->id);
        } catch (LoanRepaymentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('staf.angsuran.create')
            ->with('status', "Angsuran {$repayment->loan->loan_number} Rp ".number_format($repayment->amount, 0, ',', '.').' berhasil dibatalkan.');
    }
}
