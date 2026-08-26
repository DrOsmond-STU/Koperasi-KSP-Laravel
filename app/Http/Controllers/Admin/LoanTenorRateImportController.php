<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportLoanTenorRateRequest;
use App\Models\Loan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Koreksi massal tenor_days/tenor_unit/interest_rate_percentage pada
 * pinjaman yang sudah berjalan, lewat unggah CSV — dipakai manajer untuk
 * menyamakan data pinjaman lama (mis. hasil migrasi Saldo Awal, laporan
 * staf 26 Agu 2026: "Pinjaman Anggota" ternyata harian bukan bulanan)
 * dengan buku besar sumber, tanpa perlu mengedit baris satu per satu
 * lewat form. Sengaja generik (bukan spesifik satu produk/kasus) — bisa
 * dipakai lagi untuk koreksi tenor/tarif serupa di masa depan.
 *
 * TIDAK menyentuh loan_schedules, jurnal, atau principal_amount — murni
 * mengoreksi tiga kolom snapshot pada baris `loans`. Lewat Eloquent
 * Loan::update() (bukan raw SQL) supaya AuditableObserver tetap mencatat
 * before/after dengan aktor staf yang mengunggah (bukan null seperti
 * koreksi lewat command console).
 *
 * Baris valid tetap dikomit meski ada baris lain yang error (partial
 * commit) — sejalan dengan pola OpeningBalanceImportService.
 *
 * Variabel hasil dinamai `rowErrors` (bukan `errors`) — `errors` sudah
 * jadi nama reserved (ViewErrorBag validasi form, dari middleware
 * ShareErrorsFromSession); memakainya di sini akan menimpanya jadi null
 * dan meledakkan `@error()` di view manapun yang berbagi layout ini.
 */
class LoanTenorRateImportController extends Controller
{
    public function create(): View
    {
        $this->authorize('pinjaman.approve');

        return view('admin.pinjaman.import-tenor-tarif', ['updated' => null, 'rowErrors' => null]);
    }

    public function store(ImportLoanTenorRateRequest $request): View
    {
        $this->authorize('pinjaman.approve');

        $lines = file($request->file('file')->getRealPath());
        $rows = array_map('str_getcsv', $lines);
        $header = array_map(fn ($h) => trim(strtolower((string) $h)), array_shift($rows) ?? []);

        $updated = [];
        $rowErrors = [];

        DB::transaction(function () use ($rows, $header, &$updated, &$rowErrors) {
            foreach ($rows as $index => $row) {
                if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue; // baris kosong, mis. baris terakhir file
                }

                $data = array_combine($header, array_pad($row, count($header), null));
                $lineNumber = $index + 2; // +1 header, +1 index dari 0

                $loanNumber = trim((string) ($data['loan_number'] ?? ''));
                $tenorDays = $data['tenor_days'] ?? null;
                $tenorUnit = trim((string) ($data['tenor_unit'] ?? ''));
                $ratePercentage = $data['rate_percentage'] ?? null;

                $rowLabel = "Baris {$lineNumber}".($loanNumber !== '' ? " ({$loanNumber})" : '');

                if ($loanNumber === '') {
                    $rowErrors[] = "{$rowLabel}: loan_number kosong";

                    continue;
                }

                $loan = Loan::query()->where('loan_number', $loanNumber)->first();

                if ($loan === null) {
                    $rowErrors[] = "{$rowLabel}: pinjaman \"{$loanNumber}\" tidak ditemukan";

                    continue;
                }

                if (! is_numeric($tenorDays) || (int) $tenorDays <= 0) {
                    $rowErrors[] = "{$rowLabel}: tenor_days harus angka lebih besar dari 0";

                    continue;
                }

                if (! in_array($tenorUnit, ['hari', 'bulan'], true)) {
                    $rowErrors[] = "{$rowLabel}: tenor_unit harus \"hari\" atau \"bulan\"";

                    continue;
                }

                if (! is_numeric($ratePercentage) || (float) $ratePercentage < 0) {
                    $rowErrors[] = "{$rowLabel}: rate_percentage harus angka 0 atau lebih";

                    continue;
                }

                $before = [
                    'tenor_days' => $loan->tenor_days,
                    'tenor_unit' => $loan->tenor_unit,
                    'interest_rate_percentage' => (string) $loan->interest_rate_percentage,
                ];

                $loan->update([
                    'tenor_days' => (int) $tenorDays,
                    'tenor_unit' => $tenorUnit,
                    'interest_rate_percentage' => (float) $ratePercentage,
                ]);

                $updated[] = [
                    'loan_number' => $loanNumber,
                    'before' => $before,
                    'after' => [
                        'tenor_days' => (int) $tenorDays,
                        'tenor_unit' => $tenorUnit,
                        'interest_rate_percentage' => (float) $ratePercentage,
                    ],
                ];
            }
        });

        return view('admin.pinjaman.import-tenor-tarif', [
            'updated' => $updated,
            'rowErrors' => $rowErrors,
        ]);
    }
}
