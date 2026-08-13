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
 * Koreksi Data Saldo Awal — pengosongan massal per sub-modul.
 *
 * Halaman batch hanya bisa hapus satu baris; membetulkan import 1.224 baris
 * simpanan tidak praktis dengan cara itu. Semua aksi hanya berlaku selama
 * batch masih draft, karena setelah dikunci jurnal pembukaan sudah terbit.
 */
class OpeningBalanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    /** Saldo Awal adalah wewenang bendahara (RolePermissionSeeder). */
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

    private function isiSimpanan(OpeningBalanceBatch $batch, int $jumlah): void
    {
        $product = SavingsProduct::factory()->create();
        for ($i = 0; $i < $jumlah; $i++) {
            $batch->savings()->create([
                'member_id' => Member::factory()->create()->id,
                'savings_product_id' => $product->id,
                'balance' => 100000,
            ]);
        }
    }

    private function isiPinjamanDenganAngsuran(OpeningBalanceBatch $batch): void
    {
        $loan = $batch->loans()->create([
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

        $batch->installments()->create([
            'opening_balance_loan_id' => $loan->id,
            'installment_number' => 6,
            'due_date' => '2026-02-01',
            'principal_amount' => 900000,
            'interest_amount' => 50000,
            'penalty_amount' => 0,
            'status' => 'belum_bayar',
        ]);
    }

    public function test_halaman_koreksi_menampilkan_jumlah_baris(): void
    {
        $batch = $this->batch();
        $this->isiSimpanan($batch, 3);

        $response = $this->actingAs($this->bendahara())
            ->get(route('admin.saldo-awal.koreksi', $batch));

        $response->assertOk();
        $response->assertSee('Koreksi Data Saldo Awal');
        $response->assertSee('Simpanan');
    }

    public function test_mengosongkan_simpanan_menghapus_semua_barisnya(): void
    {
        $batch = $this->batch();
        $this->isiSimpanan($batch, 5);
        $this->assertSame(5, $batch->savings()->count());

        $response = $this->actingAs($this->bendahara())
            ->post(route('admin.saldo-awal.koreksi.clear', [$batch, 'savings']));

        $response->assertRedirect(route('admin.saldo-awal.koreksi', $batch));
        $this->assertSame(0, $batch->savings()->count());
        $this->assertStringContainsString('5 baris dihapus', session('status'));
    }

    /** Angsuran menempel pada pinjaman, jadi harus ikut terhapus. */
    public function test_mengosongkan_pinjaman_ikut_mengosongkan_angsuran(): void
    {
        $batch = $this->batch();
        $this->isiPinjamanDenganAngsuran($batch);
        $this->assertSame(1, $batch->loans()->count());
        $this->assertSame(1, $batch->installments()->count());

        $this->actingAs($this->bendahara())
            ->post(route('admin.saldo-awal.koreksi.clear', [$batch, 'loans']))
            ->assertRedirect(route('admin.saldo-awal.koreksi', $batch));

        $this->assertSame(0, $batch->loans()->count());
        $this->assertSame(0, $batch->installments()->count(), 'angsuran yatim tidak boleh tertinggal');
    }

    /** Angsuran boleh dikosongkan sendiri tanpa menyentuh pinjamannya. */
    public function test_mengosongkan_angsuran_tidak_menghapus_pinjaman(): void
    {
        $batch = $this->batch();
        $this->isiPinjamanDenganAngsuran($batch);

        $this->actingAs($this->bendahara())
            ->post(route('admin.saldo-awal.koreksi.clear', [$batch, 'installments']));

        $this->assertSame(0, $batch->installments()->count());
        $this->assertSame(1, $batch->loans()->count());
    }

    /** Setelah dikunci, saldo awal tidak boleh diubah lagi. */
    public function test_batch_terkunci_menolak_pengosongan(): void
    {
        $batch = $this->batch('locked');
        $this->isiSimpanan($batch, 2);

        $response = $this->actingAs($this->bendahara())
            ->post(route('admin.saldo-awal.koreksi.clear', [$batch, 'savings']));

        $response->assertRedirect(route('admin.saldo-awal.koreksi', $batch));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('jurnal penyesuaian', session('error'));
        $this->assertSame(2, $batch->savings()->count(), 'data batch terkunci tidak boleh hilang');
    }

    public function test_sub_modul_tidak_dikenal_menghasilkan_404(): void
    {
        $batch = $this->batch();

        $this->actingAs($this->bendahara())
            ->post(route('admin.saldo-awal.koreksi.clear', [$batch, 'ngawur']))
            ->assertNotFound();
    }

    public function test_role_tanpa_izin_ditolak(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('anggota');
        // Beri branch scope supaya route model binding berhasil dan yang diuji
        // benar-benar lapisan izin (403), bukan BranchScope yang menyembunyikan
        // batch-nya (404).
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);
        $batch = $this->batch();

        $this->actingAs($user)
            ->post(route('admin.saldo-awal.koreksi.clear', [$batch, 'savings']))
            ->assertForbidden();
    }
}
