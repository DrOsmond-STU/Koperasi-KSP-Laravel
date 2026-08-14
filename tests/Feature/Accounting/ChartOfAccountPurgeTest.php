<?php

namespace Tests\Feature\Accounting;

use App\Models\ChartOfAccount;
use App\Models\SavingsProduct;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Accounting\ChartOfAccountPurgeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hapus massal Bagan Akun — pendamping import, untuk membersihkan akun yang
 * salah import tanpa menekan Hapus satu per satu.
 *
 * Sifat yang dijaga: akun yang masih dirujuk jurnal/produk/master data lain
 * TIDAK boleh ikut terhapus, dan satu akun terkunci tidak boleh membatalkan
 * penghapusan akun lain yang sah.
 */
class ChartOfAccountPurgeTest extends TestCase
{
    use RefreshDatabase;

    /** Bagan Akun adalah wewenang admin_sistem (RolePermissionSeeder). */
    private function adminSistem(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('admin_sistem');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function akun(string $code, string $name = 'Akun Uji', ?string $parent = null): ChartOfAccount
    {
        return ChartOfAccount::query()->create([
            'code' => $code, 'name' => $name, 'type' => 'ASET',
            'normal_balance' => 'DEBIT', 'is_postable' => true,
            'parent_code' => $parent, 'statement' => 'NERACA',
        ]);
    }

    private function service(): ChartOfAccountPurgeService
    {
        return app(ChartOfAccountPurgeService::class);
    }

    /** Akun inti tidak ada otomatis di database uji — dibuat sesuai kodenya. */
    private function akunInti(): ChartOfAccount
    {
        return $this->akun(ChartOfAccount::PROTECTED_CODES[0], 'Kas');
    }

    /** Akun yang dirujuk produk simpanan lewat foreign key restrictOnDelete. */
    private function akunTerpakai(): ChartOfAccount
    {
        $akun = $this->akun('9500', 'Simpanan Sukarela');
        SavingsProduct::factory()->create(['coa_liability_account_id' => $akun->id]);

        return $akun;
    }

    public function test_akun_bebas_terhapus(): void
    {
        $a = $this->akun('9001');
        $b = $this->akun('9002');

        $hasil = $this->service()->purge([$a->id, $b->id]);

        $this->assertSame(2, $hasil['dihapus']);
        $this->assertSame([], $hasil['gagal']);
        $this->assertDatabaseMissing('chart_of_accounts', ['code' => '9001']);
        $this->assertDatabaseMissing('chart_of_accounts', ['code' => '9002']);
    }

    /** Induk dan anak yang dipilih bersama harus terhapus, apa pun urutannya. */
    public function test_induk_dan_anak_terhapus_bersama(): void
    {
        $induk = $this->akun('9000', 'Induk');
        $anak = $this->akun('9001', 'Anak', '9000');

        $hasil = $this->service()->purge([$induk->id, $anak->id]);

        $this->assertSame(2, $hasil['dihapus']);
        $this->assertDatabaseMissing('chart_of_accounts', ['code' => '9000']);
    }

    /** Induk tanpa anaknya harus ditolak, bukan menyisakan anak yatim. */
    public function test_induk_tanpa_anak_ditolak(): void
    {
        $induk = $this->akun('9000', 'Induk');
        $this->akun('9001', 'Anak', '9000');

        $hasil = $this->service()->purge([$induk->id]);

        $this->assertSame(0, $hasil['dihapus']);
        $this->assertStringContainsString('akun anak', $hasil['gagal'][0]['alasan']);
        $this->assertDatabaseHas('chart_of_accounts', ['code' => '9000']);
    }

    public function test_akun_inti_tidak_terhapus(): void
    {
        $inti = $this->akunInti();

        $hasil = $this->service()->purge([$inti->id]);

        $this->assertSame(0, $hasil['dihapus']);
        $this->assertStringContainsString('akun inti', $hasil['gagal'][0]['alasan']);
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $inti->id]);
    }

    /** Satu akun terkunci tidak boleh membatalkan penghapusan akun lain. */
    public function test_akun_terpakai_gagal_tanpa_menggagalkan_sisanya(): void
    {
        $terpakai = $this->akunTerpakai();
        $bebas = $this->akun('9001');

        $hasil = $this->service()->purge([$terpakai->id, $bebas->id]);

        $this->assertSame(1, $hasil['dihapus']);
        $this->assertCount(1, $hasil['gagal']);
        $this->assertSame($terpakai->code, $hasil['gagal'][0]['code']);
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $terpakai->id]);
        $this->assertDatabaseMissing('chart_of_accounts', ['code' => '9001']);
    }

    /** Alasan terkunci harus terbaca di layar sebelum tombol ditekan. */
    public function test_blockers_menyebut_pemakai_akun(): void
    {
        $terpakai = $this->akunTerpakai();
        $inti = $this->akunInti();

        $blockers = $this->service()->blockers();

        $this->assertContains('Produk Simpanan (akun simpanan)', $blockers[$terpakai->id]);
        $this->assertContains('akun inti sistem', $blockers[$inti->id]);
        $this->assertArrayNotHasKey($this->akun('9001')->id, $blockers);
    }

    public function test_halaman_hapus_massal_hanya_untuk_yang_berwenang(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $anggota = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $anggota->assignRole('anggota');

        $this->actingAs($anggota)
            ->get(route('admin.master.chart-of-accounts.purge.form'))
            ->assertForbidden();

        $this->actingAs($this->adminSistem())
            ->get(route('admin.master.chart-of-accounts.purge.form'))
            ->assertOk();
    }

    /** Tanpa kata kunci konfirmasi, tidak ada yang boleh terhapus. */
    public function test_konfirmasi_wajib_diketik(): void
    {
        $akun = $this->akun('9001');

        $this->actingAs($this->adminSistem())
            ->post(route('admin.master.chart-of-accounts.purge'), ['ids' => [$akun->id], 'konfirmasi' => 'hapus'])
            ->assertSessionHasErrors('konfirmasi');

        $this->assertDatabaseHas('chart_of_accounts', ['code' => '9001']);
    }

    public function test_penghapusan_lewat_layar_berjalan(): void
    {
        $akun = $this->akun('9001');

        $this->actingAs($this->adminSistem())
            ->post(route('admin.master.chart-of-accounts.purge'), ['ids' => [$akun->id], 'konfirmasi' => 'HAPUS'])
            ->assertRedirect(route('admin.master.chart-of-accounts.purge.form'));

        $this->assertDatabaseMissing('chart_of_accounts', ['code' => '9001']);
    }
}
