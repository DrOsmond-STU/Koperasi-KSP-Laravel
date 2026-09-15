<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\Loans\LoanScheduleRepairService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Perbaikan Jadwal Angsuran — tool permanen untuk pinjaman aktif yang
 * loan_schedules-nya kosong (Saldo Outstanding salah tampil Rp 0 di
 * /staf/angsuran, Bayar Angsuran Mandiri via Portal selalu ditolak
 * "overpayment"). Lihat LoanScheduleRepairService untuk penjelasan akar
 * masalah & strategi perbaikannya.
 *
 * Dibuat 27 Agu 2026 setelah insiden 79 pinjaman migrasi ditemukan tanpa
 * jadwal (backfill manual lewat investigasi database) — supaya kejadian
 * serupa berikutnya bisa dideteksi & diperbaiki sendiri oleh pengurus
 * lewat halaman ini, tanpa perlu investigasi manual ke database lagi.
 */
class LoanScheduleRepairController extends Controller
{
    public function __construct(private readonly LoanScheduleRepairService $repair) {}

    public function index(): View
    {
        $this->authorize('saldo_awal.update');

        $loans = $this->repair->findBroken();

        $previews = $loans->mapWithKeys(fn (Loan $loan) => [$loan->id => $this->repair->plan($loan)]);

        return view('admin.pinjaman.perbaikan-jadwal', [
            'loans' => $loans,
            'previews' => $previews,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('saldo_awal.update');

        $selectedIds = array_map('intval', (array) $request->input('loan_ids', []));

        // Diverifikasi ulang dari DB (bukan percaya begitu saja input
        // form) — kalau salah satu sudah diperbaiki di request lain
        // sebelum submit ini sampai, ->count() === 0 di bawah otomatis
        // mengecualikannya, jadi tidak pernah menulis jadwal dobel.
        $loans = Loan::query()
            ->where('status', 'dicairkan')
            ->whereIn('id', $selectedIds)
            ->with(['loanProduct', 'schedules'])
            ->get()
            ->filter(fn (Loan $loan) => $loan->schedules->count() === 0);

        if ($loans->isEmpty()) {
            return redirect()->route('admin.pinjaman.perbaikan-jadwal.index')
                ->with('error', 'Tidak ada pinjaman terpilih yang perlu diperbaiki (mungkin sudah diperbaiki lebih dulu, atau belum ada yang dicentang).');
        }

        $fixed = [];
        foreach ($loans as $loan) {
            $this->repair->repair($loan);
            $fixed[] = $loan->loan_number;
        }

        return redirect()->route('admin.pinjaman.perbaikan-jadwal.index')
            ->with('status', count($fixed).' pinjaman berhasil diperbaiki: '.implode(', ', $fixed).'.');
    }
}
