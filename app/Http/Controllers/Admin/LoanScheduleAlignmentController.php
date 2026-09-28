<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanScheduleAlignment;
use App\Services\Loans\LoanScheduleAlignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Layar "Penyelarasan Jadwal Angsuran" — lihat LoanScheduleAlignmentService
 * untuk akar masalah dan aturannya. Pratinjau per pinjaman ditampilkan lebih
 * dulu (sisa menurut buku besar vs jadwal, status yang akan berubah), lalu
 * pengurus memilih mana yang dijalankan; tiap penyelarasan bisa dibatalkan
 * dari riwayat. Hak akses sama dengan Perbaikan Jadwal: saldo_awal.update.
 */
class LoanScheduleAlignmentController extends Controller
{
    public function __construct(private readonly LoanScheduleAlignmentService $alignment) {}

    public function index(): View
    {
        $this->authorize('saldo_awal.update');

        return view('admin.pinjaman.penyelarasan-jadwal', [
            'rencana' => $this->alignment->temukan(),
            'riwayat' => LoanScheduleAlignment::query()
                ->with(['performedBy', 'revertedBy'])
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('saldo_awal.update');

        $request->validate([
            'loan_ids' => ['required', 'array', 'min:1'],
            'loan_ids.*' => ['integer'],
            'konfirmasi' => ['accepted'],
        ], [
            'loan_ids.required' => 'Belum ada pinjaman yang dicentang.',
            'konfirmasi.accepted' => 'Centang pernyataan persetujuan sebelum menjalankan.',
        ]);

        try {
            $hasil = $this->alignment->jalankan(
                array_map('intval', $request->input('loan_ids')),
                $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return redirect()->route('admin.pinjaman.penyelarasan-jadwal.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.pinjaman.penyelarasan-jadwal.index')
            ->with('status', sprintf(
                '%d pinjaman diselaraskan (%s baris jadwal diubah): %s. Jurnal tidak disentuh.',
                $hasil->loans_aligned,
                number_format($hasil->rows_changed, 0, ',', '.'),
                implode(', ', $hasil->nomorPinjaman()),
            ));
    }

    public function undo(Request $request, LoanScheduleAlignment $alignment): RedirectResponse
    {
        $this->authorize('saldo_awal.update');

        try {
            $this->alignment->batalkan($alignment, $request->user()->id);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.pinjaman.penyelarasan-jadwal.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.pinjaman.penyelarasan-jadwal.index')
            ->with('status', 'Penyelarasan dibatalkan — jadwal dan status pinjaman dikembalikan persis ke sebelumnya.');
    }
}
