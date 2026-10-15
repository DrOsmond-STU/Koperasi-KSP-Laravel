<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportLoanScheduleRequest;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Services\Loan\LoanScheduleImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Import massal Jadwal Angsuran untuk pinjaman yang sudah ada. Pola alurnya
 * sama dengan importir lain: validate dulu untuk membangun pratinjau baris
 * gagal, commit hanya setelah pengguna memilih All-or-nothing atau Partial.
 */
class LoanScheduleImportController extends Controller
{
    public function __construct(private readonly LoanScheduleImportService $importService) {}

    public function form(): View
    {
        $this->authorize('saldo_awal.create');

        $jumlahPinjaman = Loan::query()->count();

        return view('admin.pinjaman.import-jadwal', [
            'jumlahPinjaman' => $jumlahPinjaman,
            'jumlahJadwal' => LoanSchedule::query()->count(),
            // Pinjaman tanpa jadwal adalah alasan alat ini ada; angkanya
            // ditampilkan supaya berhasil-tidaknya import langsung terlihat.
            'tanpaJadwal' => Loan::query()->doesntHave('schedules')->count(),
        ]);
    }

    public function import(ImportLoanScheduleRequest $request): RedirectResponse
    {
        $result = $this->importService->validate($request->file('file')->getRealPath());

        if ($result->totalRows() === 0) {
            return redirect()->route('admin.pinjaman.jadwal.import.form')
                ->with('error', 'File tidak berisi baris data apa pun.');
        }

        if ($request->validated('mode') === 'all_or_nothing' && $result->hasErrors()) {
            return redirect()->route('admin.pinjaman.jadwal.import.form')
                ->with('import_errors', $result->errors)
                ->with('error', 'Import dibatalkan — '.count($result->errors).' baris bermasalah, tidak ada yang disimpan (mode All-or-nothing).');
        }

        $hasil = DB::transaction(fn () => $this->importService->commit($result->validRows));

        $pesan = "Import Jadwal Angsuran: {$hasil['dibuat']} baris jadwal untuk {$hasil['pinjaman']} pinjaman";
        $pesan .= $result->hasErrors() ? ', '.count($result->errors).' baris gagal (lihat rincian).' : '.';

        return redirect()->route('admin.pinjaman.jadwal.import.form')
            ->with('import_errors', $result->errors)
            ->with('status', $pesan);
    }
}
