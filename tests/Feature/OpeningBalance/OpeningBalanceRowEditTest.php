<?php

namespace Tests\Feature\OpeningBalance;

use App\Models\Branch;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\OpeningBalanceBatch;
use App\Models\SavingsProduct;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ubah satu baris Saldo Awal sebelum batch dikunci.
 *
 * Aturan validasinya dipakai ulang dari Store*RowRequest lewat
 * UpdateOpeningBalanceRowRequest, jadi test ini juga menjaga agar pemakaian
 * ulang itu tidak diam-diam kehilangan konteks route (mis. batasan OB-09
 * pada angsuran).
 */
class OpeningBalanceRowEditTest extends TestCase
{
    use RefreshDatabase;

    private function bendahara(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('bendahara');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function batch(string $status = 'draft'): OpeningBalanceBatch
    {
        return OpeningBalanceBatch::query()->create([
            'branch_id' => Branch::factory()->create()->id,
            'cutoff_date' => '2026-01-01',
            'status' => $status,
        ]);
    }

    private function barisSimpanan(OpeningBalanceBatch $batch)
    {
        return $batch->savings()->create([
            'member_id' => Member::factory()->create()->id,
            'savings_product_id' => SavingsProduct::factory()->create()->id,
            'account_number' => 'REK-1',
            'balance' => 100000,
        ]);
    }

    private function barisPinjaman(OpeningBalanceBatch $batch)
    {
        return $batch->loans()->create([
            'member_id' => Member::factory()->create()->id,
            'loan_product_id' => LoanProduct::factory()->create()->id,
            'external_loan_number' => 'OLD-001',
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
    }

    public function test_daftar_baris_bisa_dibuka_dan_dicari(): void
    {
        $batch = $this->batch();
        $row = $this->barisSimpanan($batch);

        $response = $this->actingAs($this->bendahara())
            ->get(route('admin.saldo-awal.koreksi.rows', [$batch, 'savings']).'?q='.$row->member->member_number);

        $response->assertOk();
        $response->assertSee($row->member->name);
    }

    public function test_form_ubah_menampilkan_nilai_sekarang(): void
    {
        $batch = $this->batch();
        $row = $this->barisSimpanan($batch);

        $response = $this->actingAs($this->bendahara())
            ->get(route('admin.saldo-awal.koreksi.edit', [$batch, 'savings', $row->id]));

        $response->assertOk();
        $response->assertSee('REK-1');
        $response->assertSee('Saldo Awal');
    }

    public function test_saldo_simpanan_bisa_dikoreksi(): void
    {
        $batch = $this->batch();
        $row = $this->barisSimpanan($batch);

        $response = $this->actingAs($this->bendahara())
            ->put(route('admin.saldo-awal.koreksi.update', [$batch, 'savings', $row->id]), [
                'member_id' => $row->member_id,
                'savings_product_id' => $row->savings_product_id,
                'account_number' => 'REK-1-REVISI',
                'balance' => 275000,
                'notes' => 'Koreksi hasil pemeriksaan bendahara',
            ]);

        $response->assertRedirect(route('admin.saldo-awal.koreksi.rows', [$batch, 'savings']));
        $this->assertDatabaseHas('opening_balance_savings', [
            'id' => $row->id,
            'account_number' => 'REK-1-REVISI',
            'balance' => 275000,
        ]);
    }

    public function test_nilai_tidak_sah_ditolak(): void
    {
        $batch = $this->batch();
        $row = $this->barisSimpanan($batch);

        $this->actingAs($this->bendahara())
            ->put(route('admin.saldo-awal.koreksi.update', [$batch, 'savings', $row->id]), [
                'member_id' => $row->member_id,
                'savings_product_id' => $row->savings_product_id,
                'balance' => -5000,
            ])
            ->assertSessionHasErrors('balance');

        $this->assertDatabaseHas('opening_balance_savings', ['id' => $row->id, 'balance' => 100000]);
    }

    public function test_pinjaman_bisa_dikoreksi(): void
    {
        $batch = $this->batch();
        $row = $this->barisPinjaman($batch);

        $this->actingAs($this->bendahara())
            ->put(route('admin.saldo-awal.koreksi.update', [$batch, 'loans', $row->id]), [
                'member_id' => $row->member_id,
                'loan_product_id' => $row->loan_product_id,
                'external_loan_number' => 'OLD-001',
                'disbursement_date' => '2025-06-01',
                'original_principal' => 10000000,
                'outstanding_principal' => 5000000,
                'outstanding_interest' => 25000,
                'tenor_months' => 12,
                'remaining_tenor_months' => 5,
                'next_installment_number' => 8,
                'next_due_date' => '2026-03-01',
                'collectibility' => 'kurang_lancar',
            ])
            ->assertRedirect(route('admin.saldo-awal.koreksi.rows', [$batch, 'loans']));

        $this->assertDatabaseHas('opening_balance_loans', [
            'id' => $row->id,
            'outstanding_principal' => 5000000,
            'collectibility' => 'kurang_lancar',
        ]);
    }

    /**
     * OB-09 harus tetap berlaku lewat form ubah: angsuran tidak boleh
     * dipindah ke pinjaman milik batch lain.
     */
    public function test_angsuran_tidak_bisa_dipindah_ke_pinjaman_batch_lain(): void
    {
        $batch = $this->batch();
        $loan = $this->barisPinjaman($batch);
        $installment = $batch->installments()->create([
            'opening_balance_loan_id' => $loan->id,
            'installment_number' => 6,
            'due_date' => '2026-02-01',
            'principal_amount' => 900000,
            'interest_amount' => 50000,
            'penalty_amount' => 0,
            'status' => 'belum_bayar',
        ]);

        $batchLain = $this->batch();
        $pinjamanBatchLain = $this->barisPinjaman($batchLain);

        $this->actingAs($this->bendahara())
            ->put(route('admin.saldo-awal.koreksi.update', [$batch, 'installments', $installment->id]), [
                'opening_balance_loan_id' => $pinjamanBatchLain->id,
                'installment_number' => 6,
                'due_date' => '2026-02-01',
                'principal_amount' => 900000,
                'interest_amount' => 50000,
                'penalty_amount' => 0,
                'status' => 'belum_bayar',
            ])
            ->assertSessionHasErrors('opening_balance_loan_id');

        $this->assertDatabaseHas('opening_balance_installments', [
            'id' => $installment->id,
            'opening_balance_loan_id' => $loan->id,
        ]);
    }

    public function test_batch_terkunci_menolak_perubahan(): void
    {
        $batch = $this->batch('locked');
        $row = $this->barisSimpanan($batch);

        $response = $this->actingAs($this->bendahara())
            ->put(route('admin.saldo-awal.koreksi.update', [$batch, 'savings', $row->id]), [
                'member_id' => $row->member_id,
                'savings_product_id' => $row->savings_product_id,
                'balance' => 999999,
            ]);

        $response->assertRedirect(route('admin.saldo-awal.koreksi', $batch));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('opening_balance_savings', ['id' => $row->id, 'balance' => 100000]);
    }

    public function test_role_tanpa_izin_tidak_bisa_mengubah(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('anggota');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $batch = $this->batch();
        $row = $this->barisSimpanan($batch);

        $this->actingAs($user)
            ->put(route('admin.saldo-awal.koreksi.update', [$batch, 'savings', $row->id]), [
                'member_id' => $row->member_id,
                'savings_product_id' => $row->savings_product_id,
                'balance' => 1,
            ])
            ->assertForbidden();
    }
}
