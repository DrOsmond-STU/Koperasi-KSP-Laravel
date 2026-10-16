<?php

namespace App\Http\Controllers\Staf;

use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Models\LoanRepayment;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Daftar Angsuran Anggota (permintaan Okt 2026) — seluruh pembayaran
 * angsuran yang sudah dicatat (loket, portal, maupun hasil impor riwayat),
 * bukan hanya "angsuran hari ini" di form Catat Angsuran. Hanya membaca:
 * pembatalan tetap lewat staf.angsuran.cancel yang sudah ada.
 *
 * Tanggal yang dipakai untuk filter, urutan, dan tampilan adalah tanggal
 * bayar (paid_at), dengan fallback ke tanggal input untuk baris lama yang
 * belum punya paid_at — sama dengan LoanRepayment::paidOn().
 *
 * Layar dipaginasi di server; cetakan memakai filter yang sama persis dan
 * memuat seluruh baris hasil filter (dibatasi BATAS_CETAK supaya dompdf
 * tidak kehabisan memori).
 */
class LoanRepaymentListController extends Controller
{
    use GeneratesPrintPdf;

    /** Pilihan "baris per halaman"; elemen pertama dipakai bila tidak valid. */
    private const PILIHAN_PER_HALAMAN = [25, 10, 50, 100];

    /** dompdf ±0,5 MB per baris — sama alasan dengan MemberController::printList(). */
    private const BATAS_CETAK = 1200;

    private const TANGGAL_BAYAR = 'COALESCE(loan_repayments.paid_at, DATE(loan_repayments.created_at))';

    public function index(Request $request): View
    {
        $this->izinkan($request);

        $filters = $this->filters($request);
        $perHalaman = in_array($request->integer('per_page'), self::PILIHAN_PER_HALAMAN, true)
            ? $request->integer('per_page')
            : self::PILIHAN_PER_HALAMAN[0];

        return view('staf.angsuran-daftar', [
            'repayments' => $this->query($filters)
                ->with(['loan.member', 'createdBy'])
                ->orderByRaw(self::TANGGAL_BAYAR.' DESC')
                ->latest('id')
                ->paginate($perHalaman)
                ->withQueryString(),
            'filters' => $filters,
            'totals' => $this->totals($filters),
            'members' => $this->memberOptions(),
            'perHalaman' => $perHalaman,
            'pilihanPerHalaman' => collect(self::PILIHAN_PER_HALAMAN)->sort()->values()->all(),
        ]);
    }

    public function print(Request $request): Response
    {
        $this->izinkan($request);
        ini_set('memory_limit', '768M');

        $filters = $this->filters($request);
        $repayments = $this->query($filters)
            ->with(['loan.member'])
            ->orderByRaw(self::TANGGAL_BAYAR.' ASC')
            ->oldest('id')
            ->limit(self::BATAS_CETAK + 1)
            ->get();

        $dipotong = $repayments->count() > self::BATAS_CETAK;

        $pdf = $this->renderPrintPdf('prints.loans.repayment-list', [
            'repayments' => $dipotong ? $repayments->take(self::BATAS_CETAK) : $repayments,
            'filters' => $filters,
            'totals' => $this->totals($filters),
            'memberLabel' => $filters['member_id']
                ? Member::query()->whereKey($filters['member_id'])->get(['member_number', 'name'])
                    ->map(fn (Member $m) => $m->member_number.' — '.$m->name)->first()
                : null,
            'dipotong' => $dipotong,
            'batasCetak' => self::BATAS_CETAK,
            'dicetakOleh' => $request->user()->name,
        ], 'landscape');

        $periode = ($filters['date_from'] ?? 'awal').'-sd-'.($filters['date_to'] ?? 'akhir');

        return $pdf->download('daftar-angsuran-anggota-'.$periode.'.pdf');
    }

    /**
     * pinjaman.read dimiliki juga oleh role Anggota (untuk Portal) — daftar
     * ini memuat angsuran SEMUA anggota, jadi akun yang hanya ber-role
     * Anggota ditolak.
     */
    private function izinkan(Request $request): void
    {
        $this->authorize('pinjaman.read');

        abort_if(
            $request->user()->getRoleNames()->diff(['anggota'])->isEmpty(),
            403,
            'Daftar Angsuran Anggota hanya untuk staf koperasi.',
        );
    }

    /**
     * @return array{q: ?string, member_id: ?int, date_from: ?string, date_to: ?string, status: ?string}
     */
    private function filters(Request $request): array
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'member_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'status' => ['nullable', 'in:aktif,dibatalkan'],
        ]);

        $dari = $request->string('date_from')->value() ?: null;
        $sampai = $request->string('date_to')->value() ?: null;

        // Rentang terbalik ditukar, bukan dibiarkan menghasilkan daftar kosong.
        if ($dari && $sampai && $dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        return [
            'q' => $request->string('q')->trim()->value() ?: null,
            'member_id' => $request->integer('member_id') ?: null,
            'date_from' => $dari ? Carbon::parse($dari)->toDateString() : null,
            'date_to' => $sampai ? Carbon::parse($sampai)->toDateString() : null,
            'status' => $request->string('status')->value() ?: null,
        ];
    }

    private function query(array $filters): Builder
    {
        return LoanRepayment::query()
            ->when($filters['q'], fn (Builder $q, string $cari) => $q->where(fn (Builder $w) => $w
                ->whereHas('loan', fn (Builder $l) => $l
                    ->where('loan_number', 'like', "%{$cari}%")
                    ->orWhereHas('member', fn (Builder $m) => $m
                        ->where('name', 'like', "%{$cari}%")
                        ->orWhere('member_number', 'like', "%{$cari}%")))
                ->orWhere('loan_repayments.description', 'like', "%{$cari}%")))
            ->when($filters['member_id'], fn (Builder $q, int $id) => $q->whereHas('loan', fn (Builder $l) => $l->where('member_id', $id)))
            ->when($filters['date_from'], fn (Builder $q, string $d) => $q->whereRaw(self::TANGGAL_BAYAR.' >= ?', [$d]))
            ->when($filters['date_to'], fn (Builder $q, string $d) => $q->whereRaw(self::TANGGAL_BAYAR.' <= ?', [$d]))
            ->when($filters['status'] === 'aktif', fn (Builder $q) => $q->whereNull('loan_repayments.cancelled_at'))
            ->when($filters['status'] === 'dibatalkan', fn (Builder $q) => $q->whereNotNull('loan_repayments.cancelled_at'));
    }

    /**
     * Rekap seluruh hasil filter (semua halaman). Angsuran yang dibatalkan
     * tidak ikut dijumlah — hanya dihitung jumlah barisnya.
     *
     * @return array{jumlah: int, jumlah_batal: int, pokok: float, jasa: float, denda: float, total: float}
     */
    private function totals(array $filters): array
    {
        $aktif = $this->query($filters)
            ->whereNull('loan_repayments.cancelled_at')
            ->selectRaw('COUNT(*) AS jumlah, COALESCE(SUM(principal_portion),0) AS pokok, COALESCE(SUM(interest_portion),0) AS jasa, COALESCE(SUM(penalty_portion),0) AS denda, COALESCE(SUM(amount),0) AS total')
            ->toBase()
            ->first();

        return [
            'jumlah' => (int) $aktif->jumlah,
            'jumlah_batal' => $filters['status'] === 'aktif' ? 0 : $this->query($filters)->whereNotNull('loan_repayments.cancelled_at')->count(),
            'pokok' => (float) $aktif->pokok,
            'jasa' => (float) $aktif->jasa,
            'denda' => (float) $aktif->denda,
            'total' => (float) $aktif->total,
        ];
    }

    private function memberOptions()
    {
        return Member::query()
            ->whereHas('loans')
            ->orderBy('name')
            ->get(['id', 'member_number', 'name']);
    }
}
