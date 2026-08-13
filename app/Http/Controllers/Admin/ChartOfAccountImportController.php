<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportChartOfAccountRequest;
use App\Models\ChartOfAccount;
use App\Services\Accounting\ChartOfAccountImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Import massal Bagan Akun. Pola alur sama dengan Saldo Awal: validate dulu
 * untuk membangun preview baris gagal, commit hanya setelah pengguna memilih
 * All-or-nothing atau Partial.
 */
class ChartOfAccountImportController extends Controller
{
    public function __construct(private readonly ChartOfAccountImportService $importService) {}

    public function form(): View
    {
        $this->authorize('chart_of_account.create');

        return view('admin.master.bagan-akun.import', [
            'jumlahAkun' => ChartOfAccount::query()->count(),
            'jumlahPostable' => ChartOfAccount::query()->where('is_postable', true)->count(),
        ]);
    }

    public function import(ImportChartOfAccountRequest $request): RedirectResponse
    {
        $result = $this->importService->validate($request->file('file')->getRealPath());

        if ($result->totalRows() === 0) {
            return redirect()->route('admin.master.chart-of-accounts.import.form')
                ->with('error', 'File tidak berisi baris data apa pun.');
        }

        if ($request->validated('mode') === 'all_or_nothing' && $result->hasErrors()) {
            return redirect()->route('admin.master.chart-of-accounts.import.form')
                ->with('import_errors', $result->errors)
                ->with('error', 'Import dibatalkan - '.count($result->errors).' baris bermasalah, tidak ada yang disimpan (mode All-or-nothing).');
        }

        $hasil = DB::transaction(fn () => $this->importService->commit($result->validRows));

        $pesan = "Import Bagan Akun: {$hasil['dibuat']} akun baru, {$hasil['diperbarui']} akun diperbarui";
        $pesan .= $result->hasErrors() ? ', '.count($result->errors).' baris gagal (lihat rincian).' : '.';

        return redirect()->route('admin.master.chart-of-accounts.index')
            ->with('import_errors', $result->errors)
            ->with('status', $pesan);
    }
}
