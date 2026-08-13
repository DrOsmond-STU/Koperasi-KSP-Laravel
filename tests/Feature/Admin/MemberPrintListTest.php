<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cetak Daftar Anggota (PDF) pada skala nyata.
 *
 * Regresi yang dijaga: dengan ~900 anggota hasil migrasi, dompdf menembus
 * memory_limit 128M milik shared hosting dan request mati sebagai PHP fatal
 * error — bukan exception, sehingga tidak muncul di log Laravel dan pengguna
 * hanya melihat halaman gagal. Perbaikannya ada di
 * MemberController::printList(): menaikkan memory_limit khusus aksi ini dan
 * hanya menghidrasi kolom yang dipakai cetakan.
 */
class MemberPrintListTest extends TestCase
{
    use RefreshDatabase;

    /** Sedikit di atas jumlah anggota hasil migrasi KPPD (918). */
    private const SKALA_NYATA = 900;

    /** Harus sama dengan ini_set() di MemberController::printList(). */
    private const BATAS_MEMORI_MB = 768;

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    public function test_daftar_anggota_pdf_berhasil_pada_skala_nyata(): void
    {
        $user = $this->manajer();
        $branch = Branch::factory()->create(['name' => 'UNIT KOPERASI (KSP)']);
        $type = MemberType::factory()->create(['name' => 'Anggota Penuh']);

        // Ukur dulu cetakan "kosong" (5 anggota) sebagai garis dasar: itu
        // biaya boot framework + PHPUnit yang TIDAK ada di server. Selisihnya
        // terhadap cetakan 900 anggota adalah biaya nyata per baris.
        Member::factory()->count(5)->create(['branch_id' => $branch->id, 'member_type_id' => $type->id]);
        memory_reset_peak_usage();
        $this->actingAs($user)->get(route('admin.members.print'))->assertOk();
        $baselineMb = memory_get_peak_usage(true) / 1024 / 1024;

        Member::factory()
            ->count(self::SKALA_NYATA - 5)
            ->create(['branch_id' => $branch->id, 'member_type_id' => $type->id]);

        // Peak di-reset supaya angka yang diukur benar-benar biaya request
        // cetak, bukan biaya pembuatan 900 anggota di atas.
        memory_reset_peak_usage();

        $response = $this->actingAs($user)->get(route('admin.members.print'));

        $peakMb = memory_get_peak_usage(true) / 1024 / 1024;
        $deltaMb = $peakMb - $baselineMb;

        fwrite(STDERR, sprintf(
            "\n[info] garis dasar (5 anggota) : %.0f MB\n[info] selisih untuk %d baris : %.0f MB (%.2f MB/100 baris)\n",
            $baselineMb, self::SKALA_NYATA, $deltaMb, $deltaMb / (self::SKALA_NYATA / 100)
        ));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        fwrite(STDERR, sprintf(
            "\n[info] puncak memori cetak %d anggota: %.0f MB (batas controller %d MB)\n",
            self::SKALA_NYATA, $peakMb, self::BATAS_MEMORI_MB
        ));

        $this->assertLessThan(
            self::BATAS_MEMORI_MB,
            $peakMb,
            'Render PDF harus muat di dalam memory_limit yang disetel MemberController::printList().'
        );
    }

    public function test_filter_cabang_dan_jenis_anggota_mempersempit_hasil(): void
    {
        $user = $this->manajer();

        $ksp = Branch::factory()->create(['name' => 'UNIT KOPERASI (KSP)']);
        $upf = Branch::factory()->create(['name' => 'UNIT PENGELOLAAN FASILITAS (UPF)']);
        $penuh = MemberType::factory()->create(['name' => 'Anggota Penuh']);

        Member::factory()->count(3)->create(['branch_id' => $ksp->id, 'member_type_id' => $penuh->id]);
        Member::factory()->count(2)->create(['branch_id' => $upf->id, 'member_type_id' => $penuh->id]);

        $response = $this->actingAs($user)->get(route('admin.members.print', [
            'branch_id' => $ksp->id,
            'member_type_id' => $penuh->id,
        ]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /**
     * Di atas ambang, cetakan ditolak dengan pesan yang bisa ditindaklanjuti
     * — bukan dibiarkan menembus memory_limit dan mati sebagai fatal error.
     */
    public function test_daftar_terlalu_besar_ditolak_dengan_pesan_bukan_fatal_error(): void
    {
        $user = $this->manajer();
        config()->set('koperasi.cetak_daftar_anggota_maks_baris', 2);

        Member::factory()->count(3)->create();

        $response = $this->actingAs($user)->get(route('admin.members.print'));

        $response->assertRedirect(route('admin.members.index'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('terlalu besar', session('error'));
        $this->assertStringContainsString('per cabang', session('error'));
    }

    /** Di bawah ambang, cetakan tetap berjalan normal. */
    public function test_tepat_di_bawah_ambang_tetap_menghasilkan_pdf(): void
    {
        $user = $this->manajer();
        config()->set('koperasi.cetak_daftar_anggota_maks_baris', 3);

        Member::factory()->count(3)->create();

        $response = $this->actingAs($user)->get(route('admin.members.print'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    /** Parameter kosong (?member_type_id=&branch_id=) diperlakukan sebagai "semua". */
    public function test_parameter_filter_kosong_diperlakukan_sebagai_semua(): void
    {
        $user = $this->manajer();
        Member::factory()->count(3)->create();

        $response = $this->actingAs($user)
            ->get(route('admin.members.print').'?member_type_id=&branch_id=');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
