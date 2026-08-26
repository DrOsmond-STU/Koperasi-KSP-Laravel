<?php

namespace Tests\Feature\Loans;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Laporan staf 26 Agu 2026: 108 pinjaman "Pinjaman Anggota" hasil migrasi
 * Saldo Awal tersimpan tenor_unit='bulan' hardcode + tarif 12% seragam,
 * padahal produknya harian dengan tarif per pinjaman dari buku besar
 * sumber (xlsx). Deploy skrip koreksi berisi 108 baris data pinjaman
 * diblokir classifier keamanan berulang kali — jalur ganti: fitur impor
 * CSV generik ini, staf sendiri yang mengunggah data koreksinya lewat UI
 * (bukan Claude yang men-deploy data finansial ke server).
 */
class LoanTenorRateImportTest extends TestCase
{
    use RefreshDatabase;

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function petugasKredit(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_kredit');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function loan(string $loanNumber, int $tenorDays = 6, string $tenorUnit = 'bulan', float $rate = 12.0): Loan
    {
        $product = LoanProduct::factory()->create();

        return Loan::factory()->create([
            'loan_product_id' => $product->id,
            'loan_number' => $loanNumber,
            'status' => 'dicairkan',
            'tenor_days' => $tenorDays,
            'tenor_unit' => $tenorUnit,
            'interest_rate_percentage' => $rate,
        ]);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('koreksi.csv', $content);
    }

    public function test_manajer_can_view_the_import_form(): void
    {
        $user = $this->manajer();

        $this->actingAs($user)->get(route('admin.pinjaman.import-tenor-tarif.create'))->assertOk();
    }

    public function test_role_without_pinjaman_approve_cannot_access_the_import(): void
    {
        $user = $this->petugasKredit();

        $this->actingAs($user)->get(route('admin.pinjaman.import-tenor-tarif.create'))->assertForbidden();
    }

    public function test_valid_csv_row_corrects_tenor_and_rate_but_leaves_principal_untouched(): void
    {
        $user = $this->manajer();
        $loan = $this->loan('117-0151-00305', tenorDays: 7, tenorUnit: 'bulan', rate: 12.0);
        $originalPrincipal = $loan->principal_amount;

        $csv = $this->csv("loan_number,tenor_days,tenor_unit,rate_percentage\n117-0151-00305,200,hari,10.0\n");

        $response = $this->actingAs($user)->post(route('admin.pinjaman.import-tenor-tarif.store'), ['file' => $csv]);

        $response->assertOk();
        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'tenor_days' => 200,
            'tenor_unit' => 'hari',
            'interest_rate_percentage' => '10.000',
            'principal_amount' => $originalPrincipal,
        ]);
    }

    public function test_unknown_loan_number_is_reported_as_an_error_without_failing_the_batch(): void
    {
        $user = $this->manajer();
        $this->loan('117-0151-00305');

        $csv = $this->csv(
            "loan_number,tenor_days,tenor_unit,rate_percentage\n".
            "117-0151-00305,200,hari,10.0\n".
            "117-0151-99999,100,hari,5.0\n"
        );

        $response = $this->actingAs($user)->post(route('admin.pinjaman.import-tenor-tarif.store'), ['file' => $csv]);

        $response->assertOk();
        $response->assertSee('117-0151-99999');
        $response->assertSee('tidak ditemukan');
        $this->assertDatabaseHas('loans', ['loan_number' => '117-0151-00305', 'tenor_unit' => 'hari']);
    }

    public function test_invalid_tenor_unit_is_rejected(): void
    {
        $user = $this->manajer();
        $loan = $this->loan('117-0151-00305', tenorDays: 7, tenorUnit: 'bulan', rate: 12.0);

        $csv = $this->csv("loan_number,tenor_days,tenor_unit,rate_percentage\n117-0151-00305,200,minggu,10.0\n");

        $response = $this->actingAs($user)->post(route('admin.pinjaman.import-tenor-tarif.store'), ['file' => $csv]);

        $response->assertOk();
        $response->assertSee('tenor_unit harus');
        $this->assertDatabaseHas('loans', ['id' => $loan->id, 'tenor_unit' => 'bulan']);
    }

    public function test_role_without_pinjaman_approve_cannot_submit_the_import(): void
    {
        $user = $this->petugasKredit();
        $csv = $this->csv("loan_number,tenor_days,tenor_unit,rate_percentage\n117-0151-00305,200,hari,10.0\n");

        $this->actingAs($user)->post(route('admin.pinjaman.import-tenor-tarif.store'), ['file' => $csv])
            ->assertForbidden();
    }
}
