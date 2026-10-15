<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Loans\LoanApprovalException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelLoanApplicationRequest;
use App\Http\Requests\CancelLoanDisbursementRequest;
use App\Http\Requests\DecideLoanApprovalRequest;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\Loans\LoanApprovalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanApprovalController extends Controller
{
    public function __construct(private readonly LoanApprovalService $approvalService) {}

    /**
     * Berapa baris pinjaman cair per halaman. Dulu layar ini memotong di 20
     * baris tanpa memberi tahu — dengan pencarian, diam-diam memotong jadi
     * jauh lebih berbahaya: staf mencari satu nomor pinjaman, tidak melihatnya,
     * lalu menyimpulkan datanya hilang padahal ia ada di baris ke-21.
     */
    private const PER_HALAMAN = 25;

    public function index(Request $request): View
    {
        $this->authorize('pinjaman.approve');

        $cari = $request->string('cari')->trim()->value();
        $produk = $request->integer('produk') ?: null;
        $cabang = $request->integer('cabang') ?: null;
        // Disaring SEKALI di sini, bukan saat dikirim ke view: kalau nilai
        // mentahnya yang dipakai kueri, ?status=diajukan menghasilkan
        // where('status','diajukan') di dalam himpunan pinjaman cair — cocok
        // dengan nol baris, dan layarnya tampak seolah datanya lenyap.
        $status = $request->string('status')->trim()->value();
        $status = in_array($status, ['dicairkan', 'dibatalkan'], true) ? $status : '';

        return view('admin.pinjaman.index', [
            'pendingLoans' => $this->saring(Loan::query()->where('status', 'diajukan'), $cari, $produk, $cabang)
                ->with(['member', 'loanProduct', 'approvals'])
                ->latest()
                ->get(),
            'cari' => $cari,
            'produk' => $produk,
            'cabang' => $cabang,
            'status' => $status,
            'daftarProduk' => LoanProduct::query()->orderBy('name')->get(['id', 'name']),
            'daftarCabang' => $this->cabangTersedia($request),
            // Dipakai layar antrian untuk menampilkan siapa yang masih bisa
            // memutus — tanpa ini staf hanya tahu setelah menekan tombol dan
            // ditolak.
            'approverNames' => User::query()
                ->get()
                ->filter(fn (User $u) => $u->can('pinjaman.approve'))
                ->pluck('name', 'id'),
            // Judulnya "Pinjaman Dicairkan Terbaru", jadi isinya harus pinjaman
            // yang uangnya pernah keluar — termasuk yang kemudian dibatalkan.
            // Pengajuan yang ditarik sebelum cair tidak pernah menyentuh kas;
            // menaruhnya di sini membuatnya seolah masih menggantung di layar
            // persetujuan, justru keluhan yang mau diperbaiki.
            //
            // Yang membedakan keduanya cuma disbursed_at, dan itu hanya diuji
            // untuk status 'dibatalkan'. Pinjaman berstatus 'dicairkan' selalu
            // tampil apa pun isi kolomnya: kalau ada baris lama yang tanggal
            // cairnya kosong, ia tetap harus terlihat, bukan hilang diam-diam.
            'disbursedLoans' => $this->saring(
                Loan::query()->where(fn ($q) => $q->where('status', 'dicairkan')
                    ->orWhere(fn ($q) => $q->where('status', 'dibatalkan')->whereNotNull('disbursed_at'))),
                $cari, $produk, $cabang
            )
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->with(['member', 'loanProduct'])
                ->latest()
                ->paginate(self::PER_HALAMAN, ['*'], 'hal')
                ->withQueryString(),
        ]);
    }

    /**
     * Saringan yang sama dipakai kedua tabel, supaya satu kali mengetik
     * mencari di antrian DAN di daftar pencairan sekaligus — staf yang
     * mengejar satu nomor pinjaman tidak perlu tahu lebih dulu pinjaman itu
     * sudah cair atau belum.
     *
     * @param  Builder<Loan>  $query
     * @return Builder<Loan>
     */
    private function saring(Builder $query, string $cari, ?int $produk, ?int $cabang): Builder
    {
        return $query
            ->when($cari !== '', fn ($q) => $q->where(function ($q) use ($cari) {
                $q->where('loan_number', 'like', "%{$cari}%")
                    ->orWhereHas('member', fn ($m) => $m->where('name', 'like', "%{$cari}%")
                        ->orWhere('member_number', 'like', "%{$cari}%"));
            }))
            ->when($produk !== null, fn ($q) => $q->where('loan_product_id', $produk))
            ->when($cabang !== null, fn ($q) => $q->where('branch_id', $cabang));
    }

    /**
     * Cabang yang boleh dilihat pengguna ini. BranchScope sudah membatasi
     * barisnya, jadi daftar ini hanya menentukan isi kotak pilihan — tanpa
     * penyaringan yang sama, pengguna satu cabang akan melihat nama cabang
     * lain di dropdown yang tidak pernah menghasilkan apa pun.
     */
    private function cabangTersedia(Request $request)
    {
        $boleh = $request->user()->allowedBranchIds();

        return Branch::query()
            ->where('is_active', true)
            ->when($boleh !== null, fn ($q) => $q->whereIn('id', $boleh))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    public function decide(DecideLoanApprovalRequest $request, Loan $loan): RedirectResponse
    {
        try {
            if ($request->validated('decision') === 'setuju') {
                $this->approvalService->approve(
                    $loan,
                    $request->user(),
                    $request->validated('notes'),
                    $request->validated('disbursed_on'),
                );
                $fresh = $loan->fresh();
                $message = $fresh->status === 'dicairkan'
                    ? "Pinjaman {$loan->loan_number} disetujui penuh dan dicairkan."
                    : "Persetujuan Anda tercatat — menunggu approval tambahan ({$fresh->approvalCount()}/{$loan->required_approval_count}).";
            } else {
                $this->approvalService->reject($loan, $request->user(), $request->validated('notes'));
                $message = "Pinjaman {$loan->loan_number} ditolak.";
            }
        } catch (LoanApprovalException $exception) {
            return redirect()->route('admin.pinjaman.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.pinjaman.index')->with('status', $message);
    }

    /**
     * Membatalkan pengajuan yang belum dicairkan. Jalur terpisah dari
     * cancel() di bawah karena keduanya menjaga hal yang berbeda: yang ini
     * menarik pengajuan yang belum jadi uang, yang itu membalik jurnal
     * pencairan yang sudah terlanjur diposting.
     */
    public function cancelApplication(CancelLoanApplicationRequest $request, Loan $loan): RedirectResponse
    {
        abort_unless($loan->canBeCancelledBy($request->user()), 403, 'Anda hanya bisa membatalkan pengajuan yang Anda buat sendiri.');

        try {
            $this->approvalService->cancelApplication($loan, $request->validated('reason'), $request->user()->id);
        } catch (LoanApprovalException $exception) {
            return redirect()->route('admin.pinjaman.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.pinjaman.index')
            ->with('status', "Pengajuan pinjaman {$loan->loan_number} berhasil dibatalkan.");
    }

    public function cancel(CancelLoanDisbursementRequest $request, Loan $loan): RedirectResponse
    {
        abort_unless($loan->canBeCancelledBy($request->user()), 403, 'Anda hanya bisa membatalkan pencairan pinjaman yang Anda buat sendiri.');

        try {
            $this->approvalService->cancelDisbursement($loan, $request->validated('reason'), $request->user()->id);
        } catch (LoanApprovalException $exception) {
            return redirect()->route('admin.pinjaman.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.pinjaman.index')->with('status', "Pencairan pinjaman {$loan->loan_number} berhasil dibatalkan.");
    }
}
