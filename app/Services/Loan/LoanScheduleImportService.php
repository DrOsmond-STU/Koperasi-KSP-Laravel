<?php

namespace App\Services\Loan;

use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Services\OpeningBalance\ImportResult;

/**
 * Import jadwal angsuran untuk pinjaman yang SUDAH ada.
 *
 * Jadwal angsuran normalnya lahir sekali saat pencairan (LoanApprovalService)
 * atau saat batch saldo awal dikunci (OpeningBalanceLockService::materializeLoans(),
 * dari opening_balance_installments). Kalau kedua jalur itu terlewat — mis.
 * batch dikunci sebelum Saldo Awal Angsuran sempat diimport — pinjaman hasil
 * migrasi berdiri tanpa jadwal sama sekali, dan batch yang sudah terkunci tidak
 * bisa diulang. Importir ini satu-satunya jalan memperbaikinya tanpa membongkar
 * saldo awal.
 *
 * TIDAK membuat jurnal apa pun. Jadwal angsuran adalah rencana pembayaran,
 * bukan peristiwa akuntansi; posisi piutangnya sudah dibukukan oleh jurnal
 * pembukaan saat batch dikunci. Menjurnal ulang di sini akan menggandakan
 * piutang dan merusak neraca.
 */
class LoanScheduleImportService
{
    public const HEADERS = [
        'no_pinjaman_lama', 'angsuran_ke', 'tanggal_jatuh_tempo',
        'nominal_pokok', 'nominal_jasa', 'nominal_denda', 'status', 'nominal_dibayar',
    ];

    /**
     * `nominal_denda` sengaja diabaikan: loan_schedules tidak punya kolom denda.
     * Denda dihitung saat pembayaran dari tarif per hari milik produk pinjaman
     * (LoanProduct::penalty_percentage_per_day), bukan disimpan di jadwal.
     */
    public const KOLOM_DIABAIKAN = ['nominal_denda'];

    private const STATUS_MAP = [
        'belum bayar' => 'belum_bayar',
        'sebagian' => 'sebagian',
        'lunas' => 'lunas',
    ];

    public function validate(string $path): ImportResult
    {
        $rows = $this->readCsv($path);

        // Nomor pinjaman dicocokkan ke loans.loan_number — saat materialisasi,
        // OpeningBalanceLockService menyalin external_loan_number ke sana, jadi
        // nomor lama tetap jadi kunci gabung yang sah setelah migrasi.
        $loans = Loan::query()->pluck('id', 'loan_number');

        $sudahAda = LoanSchedule::query()
            ->get(['loan_id', 'installment_number'])
            ->map(fn (LoanSchedule $s) => $s->loan_id.'#'.$s->installment_number)
            ->flip();

        $seen = [];
        $valid = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            [$data, $rowErrors] = $this->validateRow($row, $loans, $sudahAda, $seen);

            if ($rowErrors !== []) {
                $errors[] = ['row' => $rowNumber, 'errors' => $rowErrors];

                continue;
            }

            $seen[$data['loan_id'].'#'.$data['installment_number']] = $rowNumber;
            $valid[] = ['row' => $rowNumber, 'data' => $data];
        }

        return new ImportResult($valid, $errors);
    }

    /**
     * @param  array<int, array{row: int, data: array<string, mixed>}>  $validRows
     * @return array{dibuat: int, pinjaman: int}
     */
    public function commit(array $validRows): array
    {
        $pinjaman = [];

        foreach ($validRows as $row) {
            LoanSchedule::query()->create($row['data']);
            $pinjaman[$row['data']['loan_id']] = true;
        }

        return ['dibuat' => count($validRows), 'pinjaman' => count($pinjaman)];
    }

    /**
     * @param  array<string, string>  $row
     * @return array{0: array<string, mixed>|null, 1: array<int, string>}
     */
    private function validateRow(array $row, $loans, $sudahAda, array $seen): array
    {
        $errors = [];

        $nomor = trim((string) ($row['no_pinjaman_lama'] ?? ''));
        $loanId = $loans[$nomor] ?? null;
        if ($nomor === '') {
            $errors[] = 'no_pinjaman_lama wajib diisi';
        } elseif ($loanId === null) {
            $errors[] = 'no_pinjaman_lama "'.$nomor.'" tidak ada di daftar Pinjaman';
        }

        $angsuranKe = trim((string) ($row['angsuran_ke'] ?? ''));
        if (! ctype_digit($angsuranKe) || (int) $angsuranKe < 1) {
            $errors[] = 'angsuran_ke harus bilangan bulat mulai dari 1';
        }

        if ($loanId !== null && ctype_digit($angsuranKe)) {
            $kunci = $loanId.'#'.((int) $angsuranKe);

            if (isset($seen[$kunci])) {
                $errors[] = 'angsuran ke-'.((int) $angsuranKe).' untuk pinjaman "'.$nomor.'" duplikat dengan baris '.$seen[$kunci].' di file ini';
            } elseif (isset($sudahAda[$kunci])) {
                $errors[] = 'angsuran ke-'.((int) $angsuranKe).' untuk pinjaman "'.$nomor.'" sudah ada di jadwal — importir tidak menimpa jadwal yang sudah terbentuk';
            }
        }

        $jatuhTempo = $this->tanggal($row['tanggal_jatuh_tempo'] ?? '');
        if ($jatuhTempo === null) {
            $errors[] = 'tanggal_jatuh_tempo harus tanggal yang sah (YYYY-MM-DD atau DD/MM/YYYY)';
        }

        foreach (['nominal_pokok', 'nominal_jasa'] as $kolom) {
            if (! is_numeric($row[$kolom] ?? null)) {
                $errors[] = $kolom.' harus berupa angka';
            }
        }

        $status = self::STATUS_MAP[strtolower(trim((string) ($row['status'] ?? '')))] ?? null;
        if ($status === null) {
            $errors[] = 'status harus salah satu: Belum Bayar, Sebagian, Lunas';
        }

        $pokok = (float) ($row['nominal_pokok'] ?? 0);
        $jasa = (float) ($row['nominal_jasa'] ?? 0);
        $total = round($pokok + $jasa, 2);

        // Berkas ini tidak punya kolom nominal terbayar, jadi "Sebagian" tidak
        // bisa disimpulkan sendiri — tanpa angkanya, jadwal akan tercatat
        // sebagian terbayar sebesar nol, yang membuat sisa tagihan salah.
        $dibayarMentah = trim((string) ($row['nominal_dibayar'] ?? ''));
        $dibayar = match ($status) {
            'lunas' => $dibayarMentah !== '' ? (float) $dibayarMentah : $total,
            'sebagian' => $dibayarMentah !== '' ? (float) $dibayarMentah : null,
            default => 0.0,
        };

        if ($status === 'sebagian' && $dibayar === null) {
            $errors[] = 'status "Sebagian" wajib disertai kolom nominal_dibayar';
        }

        if ($dibayarMentah !== '' && ! is_numeric($dibayarMentah)) {
            $errors[] = 'nominal_dibayar harus berupa angka';
        }

        if ($errors !== []) {
            return [null, $errors];
        }

        return [[
            'loan_id' => $loanId,
            'installment_number' => (int) $angsuranKe,
            'due_date' => $jatuhTempo,
            'principal_amount' => $pokok,
            'interest_amount' => $jasa,
            'total_amount' => $total,
            'paid_amount' => (float) $dibayar,
            'status' => $status,
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
