<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\Member;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cetakan Pinjaman Anggota — semua anggota atau satu anggota (?member_id=),
 * gate pinjaman.print. Setiap pinjaman dilampiri historis pembayarannya
 * (LoanRepayment) lengkap + saldo outstanding pokok/jasa saat ini, supaya
 * satu cetakan ini berfungsi sebagai laporan pinjaman per anggota yang
 * sudah termasuk kartu riwayat angsurannya — tanpa perlu buka cetakan
 * jadwal-angsuran satu per satu per pinjaman (schedule()).
 */
class PrintLoanController extends Controller
{
    use GeneratesPrintPdf;

    /** Peta filter `status` di query string -> nilai kolom loans.status. */
    private const STATUS_FILTER_MAP = [
        'aktif' => 'dicairkan',
        'lunas' => 'lunas',
    ];

    public function index(Request $request): Response
    {
        $this->authorize('pinjaman.print');

        $memberId = $request->integer('member_id') ?: null;
        $branchId = $request->integer('branch_id') ?: null;
        // Default 'semua' menjaga kompatibilitas: link "Cetak Pinjaman" per
        // baris anggota (admin.anggota.index) tidak mengirim `status` sama
        // sekali, dan harus tetap menampilkan seluruh pinjaman anggota itu
        // apa pun statusnya — persis seperti sebelum filter ini ditambahkan.
        $status = $request->string('status')->toString();
        $loanStatus = self::STATUS_FILTER_MAP[$status] ?? null;

        $allowedBranchIds = $request->user()->allowedBranchIds();
        if ($branchId !== null && $allowedBranchIds !== null && ! in_array($branchId, $allowedBranchIds, true)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        $members = Member::query()
            ->with(['loans' => function ($query) use ($loanStatus) {
                $query->with(['loanProduct', 'schedules', 'repayments' => fn ($q) => $q->orderBy('created_at')])
                    ->when($loanStatus !== null, fn ($q) => $q->where('status', $loanStatus))
                    ->latest('disbursed_at');
            }])
            ->when($memberId, fn ($query) => $query->where('id', $memberId))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            // Saat status difilter, anggota yang tidak punya pinjaman dengan
            // status itu ikut disembunyikan seluruhnya — bukan cuma
            // pinjamannya kosong. Cetakan "Status: Aktif" untuk 50 anggota
            // yang 40 di antaranya lunas semua akan jadi 40 halaman
            // "Belum ada pinjaman" kalau baris ini dilewatkan.
            ->when($loanStatus !== null, fn ($query) => $query->whereHas('loans', fn ($q) => $q->where('status', $loanStatus)))
            ->orderBy('name')
            ->get();

        $filterDescription = collect([
            $memberId ? 'Anggota: '.Member::query()->find($memberId)?->name : null,
            $branchId ? 'Cabang: '.Branch::query()->find($branchId)?->name : null,
            $loanStatus !== null ? 'Status: '.ucfirst($status).' ('.$loanStatus.')' : null,
        ])->filter()->implode(' — ') ?: 'Semua Anggota';

        $pdf = $this->renderPrintPdf('prints.loans.list', [
            'members' => $members,
            'filterDescription' => $filterDescription,
            'generatedAt' => now(),
        ]);

        return $pdf->download('cetakan-pinjaman-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * Jadwal angsuran + histori pembayaran (LoanRepayment) untuk satu pinjaman.
     */
    public function schedule(Request $request, Loan $loan): Response
    {
        $this->authorize('pinjaman.print');

        $pdf = $this->renderPrintPdf('prints.loans.schedule', [
            'loan' => $loan->load('loanProduct', 'member', 'schedules'),
            'repayments' => $loan->repayments()->latest()->get(),
            'generatedAt' => now(),
        ]);

        return $pdf->download('cetakan-angsuran-'.$loan->loan_number.'.pdf');
    }
}
