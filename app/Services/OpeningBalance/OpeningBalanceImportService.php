<?php

namespace App\Services\OpeningBalance;

use App\Models\ChartOfAccount;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceCoa;
use App\Models\OpeningBalanceInstallment;
use App\Models\OpeningBalanceLoan;
use App\Models\OpeningBalanceSaving;
use App\Models\SavingsProduct;

/**
 * Parses & validates the CSV templates from 01_PRD.md Lampiran B, then
 * commits valid rows to the corresponding opening_balance_* table.
 *
 * Validation and commit are deliberately separate steps: the controller
 * calls validate*() first to build the "baris valid vs error" preview
 * (PRD §9.2 Import Wizard), and only calls commit*() once the user picks
 * All-or-nothing (commit only if zero errors) or Partial (commit the valid
 * subset regardless of errors) — see 06_TESTING.md OB-01…OB-04.
 */
class OpeningBalanceImportService
{
    private const COLLECTIBILITY_MAP = [
        'lancar' => 'lancar',
        'kurang lancar' => 'kurang_lancar',
        'diragukan' => 'diragukan',
        'macet' => 'macet',
    ];

    public function validateSavings(OpeningBalanceBatch $batch, string $path): ImportResult
    {
        [$valid, $errors] = $this->processRows($this->readCsv($path), function (array $row) {
            $rowErrors = [];

            $member = Member::query()->where('member_number', trim((string) ($row['kode_anggota'] ?? '')))->first();
            if (! $member) {
                $rowErrors[] = "kode_anggota \"{$row['kode_anggota']}\" tidak ditemukan di Master Anggota";
            }

            $product = SavingsProduct::query()->where('code', trim((string) ($row['kode_produk_simpanan'] ?? '')))->first();
            if (! $product) {
                $rowErrors[] = "kode_produk_simpanan \"{$row['kode_produk_simpanan']}\" tidak ditemukan di Master Produk Simpanan";
            }

            if (! is_numeric($row['saldo_awal'] ?? null)) {
                $rowErrors[] = 'saldo_awal harus berupa angka';
            }

            if ($rowErrors !== []) {
                return [null, $rowErrors];
            }

            return [[
                'member_id' => $member->id,
                'savings_product_id' => $product->id,
                'account_number' => ($row['no_rekening'] ?? '') !== '' ? $row['no_rekening'] : null,
                'balance' => (float) $row['saldo_awal'],
                'notes' => ($row['keterangan'] ?? '') !== '' ? $row['keterangan'] : null,
            ], []];
        });

        return new ImportResult($valid, $errors);
    }

    public function commitSavings(OpeningBalanceBatch $batch, array $validRows): int
    {
        foreach ($validRows as $row) {
            OpeningBalanceSaving::query()->create([...$row['data'], 'opening_balance_batch_id' => $batch->id]);
        }

        return count($validRows);
    }

    public function validateLoans(OpeningBalanceBatch $batch, string $path): ImportResult
    {
        [$valid, $errors] = $this->processRows($this->readCsv($path), function (array $row) {
            $rowErrors = [];

            $member = Member::query()->where('member_number', trim((string) ($row['kode_anggota'] ?? '')))->first();
            if (! $member) {
                $rowErrors[] = "kode_anggota \"{$row['kode_anggota']}\" tidak ditemukan di Master Anggota";
            }

            $product = LoanProduct::query()->where('code', trim((string) ($row['kode_produk_pinjaman'] ?? '')))->first();
            if (! $product) {
                $rowErrors[] = "kode_produk_pinjaman \"{$row['kode_produk_pinjaman']}\" tidak ditemukan di Master Produk Pinjaman";
            }

            foreach (['plafon_awal', 'sisa_pokok', 'sisa_bunga_berjalan', 'tenor_bulan', 'sisa_tenor', 'angsuran_ke'] as $numericField) {
                if (! is_numeric($row[$numericField] ?? null)) {
                    $rowErrors[] = "{$numericField} harus berupa angka";
                }
            }

            $collectibilityKey = strtolower(trim((string) ($row['kolektibilitas'] ?? '')));
            if (! isset(self::COLLECTIBILITY_MAP[$collectibilityKey])) {
                $rowErrors[] = 'kolektibilitas harus salah satu: Lancar, Kurang Lancar, Diragukan, Macet';
            }

            if ($rowErrors !== []) {
                return [null, $rowErrors];
            }

            return [[
                'member_id' => $member->id,
                'loan_product_id' => $product->id,
                'external_loan_number' => ($row['no_pinjaman_lama'] ?? '') !== '' ? $row['no_pinjaman_lama'] : null,
                'disbursement_date' => $row['tanggal_akad'],
                'original_principal' => (float) $row['plafon_awal'],
                'outstanding_principal' => (float) $row['sisa_pokok'],
                'outstanding_interest' => (float) $row['sisa_bunga_berjalan'],
                'tenor_months' => (int) $row['tenor_bulan'],
                'remaining_tenor_months' => (int) $row['sisa_tenor'],
                'next_installment_number' => (int) $row['angsuran_ke'],
                'next_due_date' => $row['tanggal_jatuh_tempo_berikutnya'],
                'collectibility' => self::COLLECTIBILITY_MAP[$collectibilityKey],
            ], []];
        });

        return new ImportResult($valid, $errors);
    }

    public function commitLoans(OpeningBalanceBatch $batch, array $validRows): int
    {
        foreach ($validRows as $row) {
            OpeningBalanceLoan::query()->create([...$row['data'], 'opening_balance_batch_id' => $batch->id]);
        }

        return count($validRows);
    }

    /**
     * OB-09: setiap baris wajib mereferensikan no_pinjaman_lama yang sudah
     * ada di opening_balance_loans pada batch yang sama.
     */
    public function validateInstallments(OpeningBalanceBatch $batch, string $path): ImportResult
    {
        [$valid, $errors] = $this->processRows($this->readCsv($path), function (array $row) use ($batch) {
            $rowErrors = [];

            $loan = OpeningBalanceLoan::query()
                ->where('opening_balance_batch_id', $batch->id)
                ->where('external_loan_number', trim((string) ($row['no_pinjaman_lama'] ?? '')))
                ->first();

            if (! $loan) {
                $rowErrors[] = "no_pinjaman_lama \"{$row['no_pinjaman_lama']}\" tidak ditemukan di Saldo Awal Pinjaman pada batch ini";
            }

            foreach (['angsuran_ke', 'nominal_pokok', 'nominal_bunga'] as $numericField) {
                if (! is_numeric($row[$numericField] ?? null)) {
                    $rowErrors[] = "{$numericField} harus berupa angka";
                }
            }

            $status = trim((string) ($row['status'] ?? ''));
            if (! in_array($status, ['Belum Bayar', 'Sebagian'], true)) {
                $rowErrors[] = 'status harus "Belum Bayar" atau "Sebagian"';
            }

            if ($rowErrors !== []) {
                return [null, $rowErrors];
            }

            return [[
                'opening_balance_loan_id' => $loan->id,
                'installment_number' => (int) $row['angsuran_ke'],
                'due_date' => $row['tanggal_jatuh_tempo'],
                'principal_amount' => (float) $row['nominal_pokok'],
                'interest_amount' => (float) $row['nominal_bunga'],
                'penalty_amount' => (float) ($row['nominal_denda'] ?? 0),
                'status' => $status === 'Sebagian' ? 'sebagian' : 'belum_bayar',
            ], []];
        });

        return new ImportResult($valid, $errors);
    }

    public function commitInstallments(OpeningBalanceBatch $batch, array $validRows): int
    {
        foreach ($validRows as $row) {
            OpeningBalanceInstallment::query()->create([...$row['data'], 'opening_balance_batch_id' => $batch->id]);
        }

        return count($validRows);
    }

    public function validateCoa(OpeningBalanceBatch $batch, string $path): ImportResult
    {
        [$valid, $errors] = $this->processRows($this->readCsv($path), function (array $row) {
            $rowErrors = [];

            $account = ChartOfAccount::query()
                ->where('code', trim((string) ($row['kode_akun'] ?? '')))
                ->where('is_postable', true)
                ->first();

            if (! $account) {
                $rowErrors[] = "kode_akun \"{$row['kode_akun']}\" tidak ditemukan atau bukan akun postable";
            }

            $position = strtolower(trim((string) ($row['posisi'] ?? '')));
            if (! in_array($position, ['debit', 'kredit'], true)) {
                $rowErrors[] = 'posisi harus Debit atau Kredit';
            }

            if (! is_numeric($row['saldo'] ?? null)) {
                $rowErrors[] = 'saldo harus berupa angka';
            }

            if ($rowErrors !== []) {
                return [null, $rowErrors];
            }

            return [[
                'chart_of_account_id' => $account->id,
                'position' => $position,
                'amount' => (float) $row['saldo'],
            ], []];
        });

        return new ImportResult($valid, $errors);
    }

    public function commitCoa(OpeningBalanceBatch $batch, array $validRows): int
    {
        foreach ($validRows as $row) {
            OpeningBalanceCoa::query()->create([...$row['data'], 'opening_balance_batch_id' => $batch->id]);
        }

        return count($validRows);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = array_map('trim', fgetcsv($handle));
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if ($line === null || $line === [null]) {
                continue;
            }

            $rows[] = array_combine($header, $line);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @param  callable(array<string, string>): array{0: array<string, mixed>|null, 1: array<int, string>}  $validate
     * @return array{0: array<int, array{row: int, data: array<string, mixed>}>, 1: array<int, array{row: int, errors: array<int, string>}>}
     */
    private function processRows(array $rows, callable $validate): array
    {
        $valid = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $rowNumber = $i + 2; // +1 for header row, +1 to be 1-indexed
            [$data, $rowErrors] = $validate($row);

            if ($rowErrors !== []) {
                $errors[] = ['row' => $rowNumber, 'errors' => $rowErrors];
            } else {
                $valid[] = ['row' => $rowNumber, 'data' => $data];
            }
        }

        return [$valid, $errors];
    }
}
