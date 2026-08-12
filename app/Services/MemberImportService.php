<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\Scopes\BranchScope;
use App\Services\OpeningBalance\ImportResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Import massal Master Anggota dari CSV.
 *
 * Mengikuti pola OpeningBalanceImportService: validate() dulu untuk
 * membangun preview "baris valid vs error", commit() hanya dipanggil setelah
 * pengguna memilih All-or-nothing (commit kalau nol error) atau Partial
 * (commit subset yang valid).
 *
 * PENTING: commit() WAJIB lewat Eloquent (Member::create), bukan query
 * builder / SQL mentah — kolom nik, address, phone, email, date_of_birth
 * memakai cast `encrypted` di App\Models\Member. Insert lewat SQL langsung
 * akan menyimpan plaintext dan membuat aplikasi gagal mendekripsi.
 */
class MemberImportService
{
    /** Header CSV template, berurutan. */
    public const HEADERS = [
        'kode_anggota',
        'nama',
        'kode_cabang',
        'kode_jenis_anggota',
        'nik',
        'alamat',
        'telepon',
        'email',
        'tanggal_lahir',
        'status',
        'tanggal_bergabung',
    ];

    /**
     * Label Indonesia di CSV -> nilai enum kolom members.status
     * (lihat StoreMemberRequest: calon|aktif|nonaktif|keluar).
     */
    private const STATUS_MAP = [
        'calon' => 'calon',
        'aktif' => 'aktif',
        'nonaktif' => 'nonaktif',
        'non aktif' => 'nonaktif',
        'tidak aktif' => 'nonaktif',
        'keluar' => 'keluar',
    ];

    /** Batas panjang mengikuti StoreMemberRequest. */
    private const MAX = [
        'member_number' => 30,
        'name' => 150,
        'nik' => 30,
        'phone' => 30,
        'email' => 150,
        'address' => 255,
    ];

    public function validate(string $path): ImportResult
    {
        $rows = $this->readCsv($path);

        $branches = Branch::query()->pluck('id', 'code');
        $memberTypes = MemberType::query()->pluck('id', 'code');

        // Cek duplikat harus melihat SELURUH anggota, bukan hanya cabang yang
        // boleh dilihat user — member_number unik lintas cabang, jadi
        // BranchScope sengaja dilepas di sini.
        $existingNumbers = Member::query()
            ->withoutGlobalScope(BranchScope::class)
            ->pluck('member_number')
            ->map(fn ($number) => $this->normalizeKey($number))
            ->flip();

        $seenInFile = [];
        $valid = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 baris header, +1 supaya 1-indexed
            [$data, $rowErrors] = $this->validateRow($row, $branches, $memberTypes, $existingNumbers, $seenInFile);

            if ($rowErrors !== []) {
                $errors[] = ['row' => $rowNumber, 'errors' => $rowErrors];

                continue;
            }

            $seenInFile[$this->normalizeKey($data['member_number'])] = $rowNumber;
            $valid[] = ['row' => $rowNumber, 'data' => $data];
        }

        return new ImportResult($valid, $errors);
    }

    /**
     * @param  array<int, array{row: int, data: array<string, mixed>}>  $validRows
     */
    public function commit(array $validRows): int
    {
        foreach ($validRows as $row) {
            Member::query()->create($row['data']);
        }

        return count($validRows);
    }

    /**
     * @param  array<string, string>  $row
     * @return array{0: array<string, mixed>|null, 1: array<int, string>}
     */
    private function validateRow(
        array $row,
        Collection $branches,
        Collection $memberTypes,
        Collection $existingNumbers,
        array $seenInFile,
    ): array {
        $rowErrors = [];

        $memberNumber = trim((string) ($row['kode_anggota'] ?? ''));
        if ($memberNumber === '') {
            $rowErrors[] = 'kode_anggota wajib diisi';
        } elseif (mb_strlen($memberNumber) > self::MAX['member_number']) {
            $rowErrors[] = 'kode_anggota maksimal '.self::MAX['member_number'].' karakter';
        } else {
            $key = $this->normalizeKey($memberNumber);
            if ($existingNumbers->has($key)) {
                $rowErrors[] = "kode_anggota \"{$memberNumber}\" sudah terdaftar di Master Anggota";
            } elseif (isset($seenInFile[$key])) {
                $rowErrors[] = "kode_anggota \"{$memberNumber}\" duplikat dengan baris {$seenInFile[$key]} di file ini";
            }
        }

        $name = trim((string) ($row['nama'] ?? ''));
        if ($name === '') {
            $rowErrors[] = 'nama wajib diisi';
        } elseif (mb_strlen($name) > self::MAX['name']) {
            $rowErrors[] = 'nama maksimal '.self::MAX['name'].' karakter';
        }

        $branchCode = trim((string) ($row['kode_cabang'] ?? ''));
        $branchId = $branches->get($branchCode);
        if ($branchId === null) {
            $rowErrors[] = "kode_cabang \"{$branchCode}\" tidak ditemukan di Master Cabang";
        }

        $typeCode = trim((string) ($row['kode_jenis_anggota'] ?? ''));
        $memberTypeId = $memberTypes->get($typeCode);
        if ($memberTypeId === null) {
            $rowErrors[] = "kode_jenis_anggota \"{$typeCode}\" tidak ditemukan di Master Jenis Anggota";
        }

        $statusKey = mb_strtolower(trim((string) ($row['status'] ?? '')));
        if (! isset(self::STATUS_MAP[$statusKey])) {
            $rowErrors[] = 'status harus salah satu: Calon, Aktif, Nonaktif, Keluar';
        }

        foreach (['nik' => 'nik', 'telepon' => 'phone', 'alamat' => 'address'] as $csvKey => $field) {
            $value = trim((string) ($row[$csvKey] ?? ''));
            if ($value !== '' && mb_strlen($value) > self::MAX[$field]) {
                $rowErrors[] = "{$csvKey} maksimal ".self::MAX[$field].' karakter';
            }
        }

        $email = trim((string) ($row['email'] ?? ''));
        if ($email !== '') {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = "email \"{$email}\" bukan alamat email yang sah";
            } elseif (mb_strlen($email) > self::MAX['email']) {
                $rowErrors[] = 'email maksimal '.self::MAX['email'].' karakter';
            }
        }

        $dates = [];
        foreach (['tanggal_lahir' => 'date_of_birth', 'tanggal_bergabung' => 'joined_at'] as $csvKey => $field) {
            $parsed = $this->parseDate($row[$csvKey] ?? null);
            if ($parsed === false) {
                $rowErrors[] = "{$csvKey} bukan tanggal yang sah (pakai format YYYY-MM-DD)";

                continue;
            }
            $dates[$field] = $parsed;
        }

        if ($rowErrors !== []) {
            return [null, $rowErrors];
        }

        return [[
            'branch_id' => $branchId,
            'member_type_id' => $memberTypeId,
            'member_number' => $memberNumber,
            'name' => $name,
            'nik' => $this->nullIfBlank($row['nik'] ?? null),
            'address' => $this->nullIfBlank($row['alamat'] ?? null),
            'phone' => $this->nullIfBlank($row['telepon'] ?? null),
            'email' => $this->nullIfBlank($row['email'] ?? null),
            'date_of_birth' => $dates['date_of_birth'] ?? null,
            'status' => self::STATUS_MAP[$statusKey],
            'joined_at' => $dates['joined_at'] ?? null,
        ], []];
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeKey(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    /**
     * @return string|null|false  null = kosong (sah), string = Y-m-d, false = tidak valid
     */
    private function parseDate(?string $raw): string|null|false
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $date = Carbon::createFromFormat($format, $value);
            // createFromFormat menerima tanggal mustahil (mis. 2026-02-31) lalu
            // menggesernya; round-trip memastikan input benar-benar sesuai format.
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = array_map('trim', fgetcsv($handle));

        // Buang BOM UTF-8 di kolom pertama — Excel menambahkannya saat
        // "Save as CSV UTF-8" dan itu membuat header pertama tidak cocok.
        if ($header !== [] && isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if ($line === null || $line === [null]) {
                continue;
            }

            // Lewati baris yang benar-benar kosong (umum di ekspor Excel).
            if (count(array_filter($line, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue;
            }

            // Samakan jumlah kolom supaya array_combine tidak error saat baris
            // pendek/panjang sebelah.
            $line = array_slice(array_pad($line, count($header), ''), 0, count($header));

            $rows[] = array_combine($header, $line);
        }

        fclose($handle);

        return $rows;
    }
}
