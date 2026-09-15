<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportLoanRepaymentHistoryRequest;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Services\Loan\LoanRepaymentHistoryImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Import riwayat pembayaran angsuran dari sistem lama.
 *
 * Berbeda dari importir lain di aplikasi ini, ukurannya puluhan ribu baris —
 * karena itu batas memori dan waktu dinaikkan di sini, dan penyimpanannya
 * memakai insert berkelompok di service.
 */
class LoanRepaymentHistoryImportController extends Controller
{
    public function __construct(private readonly LoanRepaymentHistoryImportService $importService) {}

    public function form(): View
    {
        $this->authorize('saldo_awal.create');

        return view('admin.pinjaman.import-historis', [
            'jumlahPinjaman' => Loan::query()->count(),
            'jumlahRiwayat' => LoanRepayment::query()->whereNotNull('migrated_at')->count(),
            'jumlahBerjalan' => LoanRepayment::query()->whereNull('migrated_at')->count(),
        ]);
    }

    public function import(ImportLoanRepaymentHistoryRequest $request): RedirectResponse
    {
        ini_set('memory_limit', '768M');
        set_time_limit(600);

        $result = $this->importService->validate($request->file('file')->getRealPath());

        if ($result->totalRows() === 0) {
            return redirect()->route('admin.pinjaman.historis.import.form')
                ->with('error', 'File tidak berisi baris data apa pun.');
        }

        if ($request->validated('mode') === 'all_or_nothing' && $result->hasErrors()) {
            return redirect()->route('admin.pinjaman.historis.import.form')
                // Berkas ini bisa menghasilkan ribuan baris gagal sekaligus;
                // hanya 200 pertama yang dibawa ke session supaya cookie/session
                // tidak meledak dan halamannya tetap terbuka.
                ->with('import_errors', array_slice($result->errors, 0, 200))
                ->with('error', 'Import dibatalkan — '.count($result->errors).' baris bermasalah, tidak ada yang disimpan (mode All-or-nothing).');
        }

        $hasil = DB::transaction(fn () => $this->importService->commit($result->validRows, $request->user()->id));

        $pesan = "Import Riwayat Pembayaran: {$hasil['dibuat']} baris untuk {$hasil['pinjaman']} pinjaman";
        $pesan .= $result->hasErrors() ? ', '.count($result->errors).' baris gagal (lihat rincian).' : '.';

        return redirect()->route('admin.pinjaman.historis.import.form')
            ->with('import_errors', array_slice($result->errors, 0, 200))
            ->with('status', $pesan);
    }
}
