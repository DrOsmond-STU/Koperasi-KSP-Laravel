<?php

namespace App\Services\Loan;

use App\Models\Loan;
use App\Services\OpeningBalance\ImportResult;
use Illuminate\Support\Facades\DB;

/**
 * Import riwayat pembayaran angsuran dari sistem lama.
 *
 * Sifat yang menentukan seluruh rancangan: baris-baris ini adalah ARSIP, bukan
 * transaksi. Saldo awal sudah memuat posisi akhir piutang tiap pinjaman pada
 * tanggal cutoff — angka itu sudah memperhitungkan seluruh pembayaran di masa
 * lalu. Karena itu import ini:
 *
 *   - TIDAK membuat jurnal. Menjurnal ulang akan menggandakan kas dan
 *     mengurangi piutang dua kali.
 *   - TIDAK menyentuh loan_schedules.paid_amount maupun status pinjaman.
 *     Jadwal dan sisa pokok sudah mencerminkan posisi setelah pembayaran ini.
 *   - Menandai tiap baris dengan `migrated_at`, supaya selamanya bisa
 *     dibedakan dari pembayaran yang benar-benar terjadi di aplikasi ini.
 *
 * Ukurannya juga berbeda dari importir lain — 20.874 baris, bukan ratusan.
 * Penyimpanannya memakai insert berkelompok lewat query builder, bukan
 * Eloquent per baris, karena 20 ribu model beserta event-nya akan menembus
 * batas memori dan waktu jauh sebelum selesai.
 */
class LoanRepaymentHistoryImportService
{
    public const HEADERS = [
        'no_pinjaman_lama', 'tanggal_bayar', 'nominal_pokok',
        'nominal_jasa', 'saldo_setelah', 'keterangan',
    ];

    private const UKURAN_KELOMPOK = 500;

    public function validate(string $path): ImportResult
    {
        $rows = $this->readCsv($path);

        // Nomor pinjaman lama disalin ke loans.loan_number saat materialisasi
        // batch, jadi tetap jadi kunci gabung yang sah setelah migrasi.
        $loans = Loan::query()->pluck('branch_id', 'loan_number');
        $loanIds = Loan::query()->pluck('id', 'loan_number');

        $valid = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            [$data, $rowErrors] = $this->validateRow($row, $loans, $loanIds);

            if ($rowErrors !== []) {
                $errors[] = ['row' => $rowNumber, 'errors' => $rowErrors];

                continue;
            }

            $valid[] = ['row' => $rowNumber, 'data' => $data];
        }

        return new ImportResult($valid, $errors);
    }

    /**
     * @param  array<int, array{row: int, data: array<string, mixed>}>  $validRows
     * @return array{dibuat: int, pinjaman: int}
     */
    public function commit(array $validRows, int $userId): array
    {
        $sekarang = now();
        $pinjaman = [];
        $baris = [];

        foreach ($validRows as $row) {
            $data = $row['data'];
            $pinjaman[$data['loan_id']] = true;

            $baris[] = [
                ...$data,
                'journal_entry_id' => null,
                'created_by' => $userId,
                'migrated_at' => $sekarang,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ];
        }

        foreach (array_chunk($baris, self::UKURAN_KELOMPOK) as $kelompok) {
            DB::table('loan_repayments')->insert($kelompok);
        }

        return ['dibuat' => count($baris), 'pinjaman' => count($pinjaman)];
    }

    /**
     * @param  array<string, string>  $row
     * @return array{0: array<string, mixed>|null, 1: array<int, string>}
     */
    private function validateRow(array $row, $loans, $loanIds): array
    {
        $errors = [];

        $nomor = trim((string) ($row['no_pinjaman_lama'] ?? ''));
        $loanId = $loanIds[$nomor] ?? null;
        if ($nomor === '') {
            $errors[] = 'no_pinjaman_lama wajib diisi';
        } elseif ($loanId === null) {
            $errors[] = 'no_pinjaman_lama "'.$nomor.'" tidak ada di daftar Pinjaman';
        }

        $tanggal = $this->tanggal($row['tanggal_bayar'] ?? '');
        if ($tanggal === null) {
            $errors[] = 'tanggal_bayar harus tanggal yang sah (YYYY-MM-DD atau DD/MM/YYYY)';
        }

        foreach (['nominal_pokok', 'nominal_jasa', 'saldo_setelah'] as $kolom) {
            if (! is_numeric($row[$kolom] ?? null)) {
                $errors[] = $kolom.' harus berupa angka';
            }
        }

        if ($errors !== []) {
            return [null, $errors];
        }

        $pokok = (float) $row['nominal_pokok'];
        $jasa = (float) $row['nominal_jasa'];

        return [[
            'branch_id' => $loans[$nomor],
            'loan_id' => $loanId,
            'amount' => round($pokok + $jasa, 2),
            'principal_portion' => $pokok,
            'interest_portion' => $jasa,
            'balance_after' => (float) $row['saldo_setelah'],
            'paid_at' => $tanggal,
            'description' => mb_substr(trim((string) ($row['keterangan'] ?? '')), 0, 255) ?: null,
        ], []];
    }

    private function tanggal(string $nilai): ?string
    {
        $nilai = trim($nilai);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $tanggal = \DateTimeImmutable::createFromFormat('!'.$format, $nilai);

            if ($tanggal !== false && $tanggal->format($format) === $nilai) {
                return $tanggal->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return [];
        }

        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
        $delimiter = $this->detectDelimiter($firstLine);
        $header = array_map('trim', str_getcsv(rtrim($firstLine, "\r\n"), $delimiter, '"', '\\'));

        $rows = [];

        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($line === null || $line === [null]) {
                continue;
            }

            if (count(array_filter($line, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue;
            }

            $line = array_slice(array_pad($line, count($header), ''), 0, count($header));
            $rows[] = array_combine($header, $line);
        }

        fclose($handle);

        return $rows;
    }

    private function detectDelimiter(string $headerLine): string
    {
        $counts = [
            ',' => substr_count($headerLine, ','),
            ';' => substr_count($headerLine, ';'),
            "\t" => substr_count($headerLine, "\t"),
        ];

        arsort($counts);
        $best = array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }
}
