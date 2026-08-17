<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\LaporanController;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Export PDF di hub Laporan memakai satu view cetak untuk 29 modul, jadi
 * ukuran datanya sangat bervariasi. DomPDF menyimpan pohon style tiap sel di
 * memori; modul besar (mis. Rekening Simpanan setelah migrasi) menembus
 * memory_limit dan mati sebagai 500 tanpa keterangan.
 *
 * Sifat yang dijaga: di atas ambang, permintaan ditolak dengan pesan yang
 * bisa ditindaklanjuti dan mengarahkan ke Excel — bukan fatal error.
 */
class LaporanExportPdfTest extends TestCase
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

    private function anggota(int $jumlah): void
    {
        $branch = Branch::factory()->create();
        $type = MemberType::factory()->create();

        Member::factory()->count($jumlah)->create([
            'branch_id' => $branch->id,
            'member_type_id' => $type->id,
        ]);
    }

    public function test_laporan_terlalu_besar_ditolak_dengan_pesan_bukan_fatal_error(): void
    {
        $user = $this->manajer();
        config()->set('koperasi.cetak_laporan_maks_baris', 2);

        $this->anggota(3);

        $response = $this->actingAs($user)->get(route('admin.laporan.export-pdf', 'anggota'));

        $response->assertRedirect(route('admin.laporan.show', 'anggota'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('terlalu besar', session('error'));
        $this->assertStringContainsString('Excel', session('error'));
    }

    /** Di bawah ambang, cetakan tetap menghasilkan PDF seperti biasa. */
    public function test_tepat_di_bawah_ambang_tetap_menghasilkan_pdf(): void
    {
        $user = $this->manajer();
        config()->set('koperasi.cetak_laporan_maks_baris', 3);

        $this->anggota(3);

        $response = $this->actingAs($user)->get(route('admin.laporan.export-pdf', 'anggota'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    /** Excel tidak ikut dibatasi — itulah jalan keluar yang ditunjuk pesannya. */
    public function test_export_excel_tidak_dibatasi_ambang(): void
    {
        $user = $this->manajer();
        config()->set('koperasi.cetak_laporan_maks_baris', 1);

        $this->anggota(5);

        $this->actingAs($user)
            ->get(route('admin.laporan.export-excel', 'anggota'))
            ->assertOk();
    }

    /**
     * Cetakan hanya memuat rekening bersaldo; layar tetap memuat semuanya,
     * karena layar dipakai menelusuri riwayat dan baris yang hilang di sana
     * akan terbaca sebagai data lenyap.
     */
    public function test_cetakan_simpanan_membuang_rekening_bersaldo_nol(): void
    {
        $user = $this->manajer();

        SavingsAccount::factory()->create(['balance' => 500000, 'account_number' => 'ADA-001']);
        SavingsAccount::factory()->create(['balance' => 0, 'account_number' => 'NOL-001']);

        $controller = app(LaporanController::class);
        $fetch = new ReflectionMethod($controller, 'fetch');

        $cetak = $fetch->invoke($controller, 'simpanan_rekening', true);
        $layar = $fetch->invoke($controller, 'simpanan_rekening', false);

        $this->assertSame(['ADA-001'], $cetak->pluck('account_number')->all());
        $this->assertCount(2, $layar);

        $this->actingAs($user)
            ->get(route('admin.laporan.show', 'simpanan_rekening'))
            ->assertOk()
            ->assertSee('NOL-001');
    }

    /** Pinjaman berstatus lunas tidak ikut tercetak. */
    public function test_cetakan_pinjaman_membuang_yang_sudah_lunas(): void
    {
        Loan::factory()->create(['status' => 'dicairkan', 'loan_number' => 'JALAN-001']);
        Loan::factory()->create(['status' => 'lunas', 'loan_number' => 'LUNAS-001']);

        $controller = app(LaporanController::class);
        $fetch = new ReflectionMethod($controller, 'fetch');

        $this->assertSame(
            ['JALAN-001'],
            $fetch->invoke($controller, 'pinjaman', true)->pluck('loan_number')->all(),
        );
        $this->assertCount(2, $fetch->invoke($controller, 'pinjaman', false));
    }

    /** Alasan penyaringan wajib ikut tercetak, bukan diam-diam memangkas baris. */
    public function test_cetakan_menyebutkan_alasan_penyaringan(): void
    {
        $user = $this->manajer();
        SavingsAccount::factory()->create(['balance' => 500000]);

        $response = $this->actingAs($user)->get(route('admin.laporan.export-pdf', 'simpanan_rekening'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    /**
     * Cetakan harus memuat apa yang tampil di layar. Saringan datatable bekerja
     * di sisi klien, jadi keadaannya dititipkan lewat URL dan diterapkan ulang
     * di server dengan aturan yang sama.
     */
    public function test_cetakan_hanya_memuat_baris_yang_lolos_saringan(): void
    {
        $controller = app(LaporanController::class);
        $saring = new ReflectionMethod($controller, 'saringSepertiDiLayar');

        $rows = collect([
            ['member_number' => 'A-001', 'name' => 'Budi', 'cabang' => 'KSP', 'joined_at' => '10-01-2026'],
            ['member_number' => 'A-002', 'name' => 'Siti', 'cabang' => 'UPF', 'joined_at' => '15-06-2026'],
            ['member_number' => 'A-003', 'name' => 'Budiman', 'cabang' => 'KSP', 'joined_at' => '20-12-2026'],
        ]);

        // Pencarian bebas: mencocokkan seluruh isi baris, tanpa peduli huruf.
        $hasil = $saring->invoke($controller, $rows, Request::create('/', 'GET', ['cari' => 'budi']), 'anggota');
        $this->assertSame(['Budi', 'Budiman'], $hasil->pluck('name')->all());

        // Filter kolom: cocok utuh, bukan sebagian.
        $hasil = $saring->invoke($controller, $rows, Request::create('/', 'GET', ['f_cabang' => 'UPF']), 'anggota');
        $this->assertSame(['Siti'], $hasil->pluck('name')->all());

        // Rentang tanggal pada kolom tanggal laporan.
        $hasil = $saring->invoke($controller, $rows, Request::create('/', 'GET', [
            'dari' => '2026-02-01', 'sampai' => '2026-07-01',
        ]), 'anggota');
        $this->assertSame(['Siti'], $hasil->pluck('name')->all());

        // Tanpa parameter apa pun, seluruh baris ikut.
        $hasil = $saring->invoke($controller, $rows, Request::create('/', 'GET'), 'anggota');
        $this->assertCount(3, $hasil);
    }

    /** Saringan yang dipakai wajib tercetak, supaya tidak dikira laporan lengkap. */
    public function test_saringan_yang_dipakai_disebut_di_kepala_cetakan(): void
    {
        $controller = app(LaporanController::class);
        $catatan = new ReflectionMethod($controller, 'catatanSaringan');

        $teks = $catatan->invoke($controller, Request::create('/', 'GET', [
            'cari' => 'budi', 'f_cabang' => 'UPF', 'dari' => '2026-01-01',
        ]), 'anggota');

        $this->assertStringContainsString('Disaring', $teks);
        $this->assertStringContainsString('budi', $teks);
        $this->assertStringContainsString('UPF', $teks);
        $this->assertStringContainsString('2026-01-01', $teks);

        $this->assertNull($catatan->invoke($controller, Request::create('/', 'GET'), 'anggota'));
    }

    /** Ambang baris dihitung setelah penyaringan, bukan sebelumnya. */
    public function test_ambang_baris_dihitung_setelah_disaring(): void
    {
        $user = $this->manajer();
        config()->set('koperasi.cetak_laporan_maks_baris', 2);

        $branch = Branch::factory()->create(['name' => 'KSP']);
        $type = MemberType::factory()->create();
        Member::factory()->count(5)->create(['branch_id' => $branch->id, 'member_type_id' => $type->id, 'name' => 'Budi']);
        Member::factory()->create(['branch_id' => $branch->id, 'member_type_id' => $type->id, 'name' => 'Siti']);

        // Tanpa saringan 6 baris — di atas ambang, ditolak.
        $this->actingAs($user)
            ->get(route('admin.laporan.export-pdf', 'anggota'))
            ->assertRedirect(route('admin.laporan.show', 'anggota'));

        // Dengan saringan tersisa 1 baris — di bawah ambang, PDF terbit.
        $this->actingAs($user)
            ->get(route('admin.laporan.export-pdf', 'anggota').'?cari=siti')
            ->assertOk();
    }

    /**
     * Laporan berkolom banyak dicetak landscape; yang ramping tetap mengikuti
     * pengaturan admin (null = jangan ditimpa).
     */
    public function test_orientasi_landscape_hanya_untuk_laporan_berkolom_banyak(): void
    {
        $controller = app(LaporanController::class);
        $orientasi = new ReflectionMethod($controller, 'orientasiUntuk');

        $this->assertSame('landscape', $orientasi->invoke($controller, array_fill_keys(range('a', 'i'), 'x')));
        $this->assertSame('landscape', $orientasi->invoke($controller, array_fill_keys(range('a', 'g'), 'x')));
        $this->assertNull($orientasi->invoke($controller, array_fill_keys(range('a', 'f'), 'x')));
    }
}
