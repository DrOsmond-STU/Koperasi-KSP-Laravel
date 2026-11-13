<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Services\OpeningBalance\ImportResult;

/**
 * Import Bagan Akun dari CSV — untuk membawa bagan akun koperasi yang sudah
 * berjalan (mis. 262 akun dari SMIK) tanpa mengetiknya satu per satu.
 *
 * Berbeda dengan ChartOfAccountsSeeder yang MENGHAPUS seluruh isi tabel
 * sebelum mengisi, importer ini bersifat tambah/perbarui per `code`. Itu
 * disengaja: bagan bawaan sudah dirujuk produk simpanan/pinjaman lewat
 * foreign key, dan enam kode di ChartOfAccount::PROTECTED_CODES di-hardcode
 * di sejumlah service inti — menghapusnya akan melumpuhkan posting kas,
 * koreksi, dan pelepasan aset di seluruh aplikasi.
 */
class ChartOfAccountImportService
{
    public const HEADERS = [
        'code', 'name', 'type', 'group', 'normal_balance',
        'is_postable', 'parent_code', 'statement', 'notes',
    ];

    private const TYPES = ['ASET', 'LIABILITAS', 'EKUITAS', 'PENDAPATAN', 'BEBAN'];

    private const BALANCES = ['DEBIT', 'KREDIT'];

    private const STATEMENTS = ['NERACA', 'LABA_RUGI'];

    public function validate(string $path): ImportResult
    {
        $rows = $this->readCsv($path);

        // parent_code boleh menunjuk akun di file yang sama ATAU akun yang
        // sudah ada di database — keduanya sah.
        $codesInFile = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            if ($code !== '') {
                $codesInFile[$code] = true;
            }
        }
        $existingCodes = ChartOfAccount::query()->pluck('code')->flip()->all();

        $seen = [];
        $valid = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            [$data, $rowErrors] = $this->validateRow($row, $codesInFile, $existingCodes, $seen);

            if ($rowErrors !== []) {
                $errors[] = ['row' => $rowNumber, 'errors' => $rowErrors];

                continue;
            }

            $seen[$data['code']] = $rowNumber;
            $valid[] = ['row' => $rowNumber, 'data' => $data];
        }

        return new ImportResult($valid, $errors);
    }

    /**
     * @param  array<int, array{row: int, data: array<string, mixed>}>  $validRows
     * @return array{dibuat: int, diperbarui: int}
     */
    public function commit(array $validRows): array
    {
        $dibuat = 0;
        $diperbarui = 0;

        foreach ($validRows as $row) {
            $data = $row['data'];
            $existing = ChartOfAccount::query()->where('code', $data['code'])->first();

            if ($existing) {
                $existing->update($data);
                $diperbarui++;

                continue;
            }

            ChartOfAccount::query()->create($data);
            $dibuat++;
        }

        return ['dibuat' => $dibuat, 'diperbarui' => $diperbarui];
    }

    /**
     * @param  array<string, string>  $row
     * @return array{0: array<string, mixed>|null, 1: array<int, string>}
     */
    private function validateRow(array $row, array $codesInFile, array $existingCodes, array $seen): array
    {
        $errors = [];

        $code = trim((string) ($row['code'] ?? ''));
        if ($code === '') {
            $errors[] = 'code wajib diisi';
        } elseif (mb_strlen($code) > 10) {
            $errors[] = 'code maksimal 10 karakter';
        } elseif (isset($seen[$code])) {
            $errors[] = 'code "'.$code.'" duplikat dengan baris '.$seen[$code].' di file ini';
        } elseif (in_array($code, ChartOfAccount::PROTECTED_CODES, true)) {
            $errors[] = 'code "'.$code.'" adalah akun inti yang dipakai langsung oleh sistem dan tidak boleh ditimpa lewat import';
        }

        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            $errors[] = 'name wajib diisi';
        }

        $type = strtoupper(trim((string) ($row['type'] ?? '')));
        if (! in_array($type, self::TYPES, true)) {
            $errors[] = 'type harus salah satu: '.implode(', ', self::TYPES);
        }

        $balance = strtoupper(trim((string) ($row['normal_balance'] ?? '')));
        if (! in_array($balance, self::BALANCES, true)) {
            $errors[] = 'normal_balance harus DEBIT atau KREDIT';
        }

        $statement = strtoupper(trim((string) ($row['statement'] ?? '')));
        if (! in_array($statement, self::STATEMENTS, true)) {
            $errors[] = 'statement harus NERACA atau LABA_RUGI';
        }

        $parent = trim((string) ($row['parent_code'] ?? ''));
        if ($parent !== '' && ! isset($codesInFile[$parent]) && ! isset($existingCodes[$parent])) {
            $errors[] = 'parent_code "'.$parent.'" tidak ada di file ini maupun di Bagan Akun';
        }
        if ($parent !== '' && $parent === $code) {
            $errors[] = 'parent_code tidak boleh menunjuk dirinya sendiri';
        }

        if ($errors !== []) {
            return [null, $errors];
        }

        return [[
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'group' => ($row['group'] ?? '') !== '' ? trim((string) $row['group']) : null,
            'normal_balance' => $balance,
            'is_postable' => strtoupper(trim((string) ($row['is_postable'] ?? ''))) === 'TRUE',
            'parent_code' => $parent !== '' ? $parent : null,
            'statement' => $statement,
            'notes' => ($row['notes'] ?? '') !== '' ? trim((string) $row['notes']) : null,
        ], []];
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
