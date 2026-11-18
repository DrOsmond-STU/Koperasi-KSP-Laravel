<?php

namespace App\Http\Controllers\Staf;

use App\Exceptions\Loans\InvalidLoanApplicationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitLoanApplicationRequest;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Services\Loans\LoanBranchResolver;
use App\Services\Loans\LoanScheduleCalculator;
use App\Services\Loans\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class LoanApplicationController extends Controller
{
    public function __construct(
        private readonly LoanService $loanService,
        private readonly LoanScheduleCalculator $scheduleCalculator,
        private readonly LoanBranchResolver $loanBranches,
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

            // Batas bawah isian tanggal diambil dari sumber yang sama dengan
            // validasi server, supaya batas di peramban tidak pernah berselisih
            // dengan batas yang sebenarnya diberlakukan saat submit.
            'tanggalPalingAwal' => SubmitLoanApplicationRequest::tanggalPalingAwal()->toDateString(),
            'labelCutoff' => Carbon::parse(SubmitLoanApplicationRequest::CUTOFF_SALDO_AWAL)
                ->translatedFormat('d F Y'),
        ]);
    }

    /**
     * Simulasi jadwal angsuran pakai tarif produk yang berlaku saat ini,
     * tanpa menyimpan apa pun — langkah "Simulasi & scoring" (PRD §8).
     *
     * Jadwal dihitung mulai TANGGAL PENGAJUAN, bukan mulai hari ini. Untuk
     * pengajuan biasa keduanya sama saja; untuk pinjaman lama yang baru
     * dicatat, inilah yang membuat pratinjau di sini sama persis dengan
     * jadwal yang nanti terbentuk saat pencairan (lihat
     * LoanApprovalService::disburse(), yang juga memakai submitted_at).
     */
    public function simulate(SubmitLoanApplicationRequest $request): View
    {
        $member = Member::query()->findOrFail($request->validated('member_id'));
        $product = LoanProduct::query()->findOrFail($request->validated('loan_product_id'));
        $principal = (float) $request->validated('principal_amount');
        $tenor = (int) $request->validated('tenor_days');
        $submittedAt = $this->tanggalPengajuan($request);
        $rate = $product->rateAt();

        $ratePercentage = (float) ($rate?->rate_percentage ?? 0);

        $schedule = $product->usesDailyTenor()
            ? $this->scheduleCalculator->calculateDaily($principal, $tenor, $ratePercentage, $product->calculation_method, $submittedAt)
            : $this->scheduleCalculator->calculate($principal, $tenor, $ratePercentage, $product->calculation_method, $submittedAt);

        return view('staf.pengajuan-pinjaman-simulasi', [
            'member' => $member,
            'product' => $product,
            'principal' => $principal,
            'tenor' => $tenor,
            'submittedAt' => $submittedAt,
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
                // Cabang unit yang menjalankan pinjaman, bukan cabang tempat
                // anggotanya terdaftar — lihat LoanBranchResolver.
                $this->loanBranches->resolveOrFail($member->branch_id),
                $request->user()->id,
                $this->tanggalPengajuan($request),
            );
        } catch (InvalidLoanApplicationException $exception) {
            return redirect()
                ->route('staf.pengajuan-pinjaman.create')
                ->with('error', $exception->getMessage());
        }

        $catatan = $loan->submitted_at->isToday()
            ? ''
            : ' (tanggal pengajuan '.$loan->submitted_at->translatedFormat('d M Y').')';

        return redirect()
            ->route('staf.pengajuan-pinjaman.create')
            ->with('status', "Pengajuan pinjaman {$loan->loan_number} berhasil dikirim, menunggu persetujuan.{$catatan}");
    }

    /**
     * Tanggal pengajuan yang dipakai seluruh alur. Kosong berarti hari ini,
     * sehingga pengajuan biasa di loket berperilaku persis seperti sebelumnya.
     */
    private function tanggalPengajuan(SubmitLoanApplicationRequest $request): Carbon
    {
        $nilai = $request->validated('submitted_at');

        return $nilai ? Carbon::parse($nilai)->startOfDay() : Carbon::now();
    }
}
