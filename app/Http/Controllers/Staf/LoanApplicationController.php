<?php

namespace App\Http\Controllers\Staf;

use App\Exceptions\Loans\InvalidLoanApplicationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitLoanApplicationRequest;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Services\Loans\LoanScheduleCalculator;
use App\Services\Loans\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoanApplicationController extends Controller
{
    public function __construct(
        private readonly LoanService $loanService,
        private readonly LoanScheduleCalculator $scheduleCalculator,
    ) {}

    public function create(): View
    {
        $this->authorize('pinjaman.create');

        return view('staf.pengajuan-pinjaman', [
            'members' => Member::query()->where('status', 'aktif')->get(),
            // Semua produk aktif berlaku untuk pengajuan baru — hari
            // (mis. Pinjaman Anggota) maupun bulan (mis. Piutang
            // Karyawan) hidup berdampingan, lihat LoanProduct::usesDailyTenor().
            'products' => LoanProduct::query()->where('is_active', true)->get(),
        ]);
    }

    /**
     * Simulasi jadwal angsuran pakai tarif produk yang berlaku saat ini,
     * tanpa menyimpan apa pun — langkah "Simulasi & scoring" (PRD §8).
     */
    public function simulate(SubmitLoanApplicationRequest $request): View
    {
        $member = Member::query()->findOrFail($request->validated('member_id'));
        $product = LoanProduct::query()->findOrFail($request->validated('loan_product_id'));
        $principal = (float) $request->validated('principal_amount');
        $tenor = (int) $request->validated('tenor_days');
        $rate = $product->rateAt();

        $ratePercentage = (float) ($rate?->rate_percentage ?? 0);

        $schedule = $product->usesDailyTenor()
            ? $this->scheduleCalculator->calculateDaily($principal, $tenor, $ratePercentage, $product->calculation_method, now())
            : $this->scheduleCalculator->calculate($principal, $tenor, $ratePercentage, $product->calculation_method, now());

        return view('staf.pengajuan-pinjaman-simulasi', [
            'member' => $member,
            'product' => $product,
            'principal' => $principal,
            'tenor' => $tenor,
            'ratePercentage' => $ratePercentage,
            'schedule' => $schedule,
        ]);
    }

    public function store(SubmitLoanApplicationRequest $request): RedirectResponse
    {
        $member = Member::query()->findOrFail($request->validated('member_id'));
        $product = LoanProduct::query()->findOrFail($request->validated('loan_product_id'));

        // Laporan staf 26 Agu 2026: submit pengajuan dengan plafon/tenor di
        // luar rentang produk menghasilkan 500 (sebelumnya tidak ditangkap
        // di sini) — simulate() di atas TIDAK memvalidasi rentang produk
        // (murni pratinjau jadwal), jadi staf baru "kena" validasi ini saat
        // submit akhir. Pola tangkap-dan-redirect sama dengan
        // PosController::store() yang sudah menangkap exception yang sama.
        try {
            $loan = $this->loanService->submitApplication(
                $member,
                $product,
                (float) $request->validated('principal_amount'),
                (int) $request->validated('tenor_days'),
                $member->branch_id,
                $request->user()->id,
            );
        } catch (InvalidLoanApplicationException $exception) {
            return redirect()
                ->route('staf.pengajuan-pinjaman.create')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('staf.pengajuan-pinjaman.create')
            ->with('status', "Pengajuan pinjaman {$loan->loan_number} berhasil dikirim, menunggu persetujuan.");
    }
}
