<?php

namespace Tests\Feature\OpeningBalance;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceLoan;
use App\Models\SavingsProduct;
use App\Services\OpeningBalance\OpeningBalanceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md OB-01, OB-02, OB-09 (validation-level behaviour of
 * the CSV import, independent of the HTTP layer).
 */
class OpeningBalanceImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function csvFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ob_test_').'.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'import.csv', 'text/csv', null, true);
    }

    private function batch(): OpeningBalanceBatch
    {
        return OpeningBalanceBatch::query()->create([
            'branch_id' => Branch::factory()->create()->id,
            'cutoff_date' => '2026-01-01',
            'status' => 'draft',
        ]);
    }

    /** OB-01: valid rows are parsed successfully with no errors. */
    public function test_valid_savings_csv_has_no_errors(): void
    {
        $member = Member::factory()->create(['member_number' => 'AGT-0001']);
        $product = SavingsProduct::factory()->create(['code' => 'SIM-SUKARELA']);
        $batch = $this->batch();

        $csv = "kode_anggota,kode_produk_simpanan,no_rekening,saldo_awal,tanggal_saldo_awal,keterangan\n"
            ."AGT-0001,SIM-SUKARELA,,1500000.00,2026-01-01,Migrasi\n";

        $result = app(OpeningBalanceImportService::class)->validateSavings($batch, $this->csvFile($csv)->getRealPath());

        $this->assertFalse($result->hasErrors());
        $this->assertCount(1, $result->validRows);
        $this->assertEquals($member->id, $result->validRows[0]['data']['member_id']);
        $this->assertEquals($product->id, $result->validRows[0]['data']['savings_product_id']);
        $this->assertEquals(1500000.0, $result->validRows[0]['data']['balance']);
    }

    /** OB-02: unknown kode_anggota / kode_produk_simpanan produce per-row errors. */
    public function test_savings_csv_with_unknown_references_produces_errors(): void
    {
        $batch = $this->batch();

        $csv = "kode_anggota,kode_produk_simpanan,no_rekening,saldo_awal,tanggal_saldo_awal,keterangan\n"
            ."AGT-TIDAK-ADA,SIM-TIDAK-ADA,,1500000.00,2026-01-01,\n";

        $result = app(OpeningBalanceImportService::class)->validateSavings($batch, $this->csvFile($csv)->getRealPath());

        $this->assertTrue($result->hasErrors());
        $this->assertCount(0, $result->validRows);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('kode_anggota', $result->errors[0]['errors'][0]);
    }

    public function test_savings_csv_with_non_numeric_balance_is_an_error(): void
    {
        $member = Member::factory()->create(['member_number' => 'AGT-0002']);
        SavingsProduct::factory()->create(['code' => 'SIM-WAJIB']);
        $batch = $this->batch();

        $csv = "kode_anggota,kode_produk_simpanan,no_rekening,saldo_awal,tanggal_saldo_awal,keterangan\n"
            ."AGT-0002,SIM-WAJIB,,bukan-angka,2026-01-01,\n";

        $result = app(OpeningBalanceImportService::class)->validateSavings($batch, $this->csvFile($csv)->getRealPath());

        $this->assertTrue($result->hasErrors());
    }

    /** OB-09: an installment row must reference an existing loan in the same batch. */
    public function test_installments_csv_rejects_unknown_loan_reference(): void
    {
        $batch = $this->batch();

        $csv = "kode_anggota,no_pinjaman_lama,angsuran_ke,tanggal_jatuh_tempo,nominal_pokok,nominal_jasa,nominal_denda,status\n"
            ."AGT-0001,OLD-TIDAK-ADA,6,2026-02-01,900000,50000,0,Belum Bayar\n";

        $result = app(OpeningBalanceImportService::class)->validateInstallments($batch, $this->csvFile($csv)->getRealPath());

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('no_pinjaman_lama', $result->errors[0]['errors'][0]);
    }

    public function test_installments_csv_accepts_row_matching_loan_in_same_batch(): void
    {
        $batch = $this->batch();
        $member = Member::factory()->create();
        $product = LoanProduct::factory()->create();

        OpeningBalanceLoan::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'external_loan_number' => 'OLD-00234',
            'disbursement_date' => '2025-06-01',
            'original_principal' => 10000000,
            'outstanding_principal' => 6500000,
            'outstanding_interest' => 50000,
            'tenor_months' => 12,
            'remaining_tenor_months' => 7,
            'next_installment_number' => 6,
            'next_due_date' => '2026-02-01',
            'collectibility' => 'lancar',
        ]);

        $csv = "kode_anggota,no_pinjaman_lama,angsuran_ke,tanggal_jatuh_tempo,nominal_pokok,nominal_jasa,nominal_denda,status\n"
            ."AGT-0001,OLD-00234,6,2026-02-01,900000,50000,0,Belum Bayar\n";

        $result = app(OpeningBalanceImportService::class)->validateInstallments($batch, $this->csvFile($csv)->getRealPath());

        $this->assertFalse($result->hasErrors());
        $this->assertCount(1, $result->validRows);
    }

    public function test_coa_csv_rejects_non_postable_account(): void
    {
        $account = ChartOfAccount::factory()->nonPostable()->create(['code' => '1100']);
        $batch = $this->batch();

        $csv = "kode_akun,posisi,saldo,tanggal_saldo_awal\n"
            .'1100,Debit,50000000,2026-01-01'."\n";

        $result = app(OpeningBalanceImportService::class)->validateCoa($batch, $this->csvFile($csv)->getRealPath());

        $this->assertTrue($result->hasErrors());
    }
}
