<?php

namespace Tests\Feature\Loan;

use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Loan\LoanScheduleImportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Import Jadwal Angsuran untuk pinjaman yang sudah ada.
 *
 * Jadwal normalnya lahir saat pencairan atau saat batch saldo awal dikunci.
 * Kalau kedua jalur itu terlewat, pinjaman hasil migrasi berdiri tanpa jadwal
 * dan batch yang terkunci tidak bisa diulang — importir ini satu-satunya jalan
 * memperbaikinya.
 *
 * Sifat yang dijaga: TIDAK membuat jurnal (piutangnya sudah dibukukan jurnal
 * pembukaan), dan tidak pernah menimpa jadwal yang sudah terbentuk.
 */
class LoanScheduleImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'no_pinjaman_lama,angsuran_ke,tanggal_jatuh_tempo,nominal_pokok,nominal_jasa,nominal_denda,status';

    private function bendahara(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('bendahara');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function service(): LoanScheduleImportService
    {
        return app(LoanScheduleImportService::class);
    }

    private function csvPath(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jadwal_').'.csv';
        file_put_contents($path, $content);

        return $path;
    }

    private function pinjaman(string $nomor): Loan
    {
        return Loan::factory()->create(['loan_number' => $nomor, 'status' => 'dicairkan']);
    }

    public function test_jadwal_terbentuk_untuk_pinjaman_yang_ada(): void
    {
        $this->actingAs($this->bendahara());
        $loan = $this->pinjaman('117-0151-00045');

        $csv = self::HEADER."\n"
            .'117-0151-00045,1,2026-09-10,350000.00,49700.00,0,Belum Bayar'."\n"
            .'117-0151-00045,2,2026-10-10,350000.00,49700.00,0,Belum Bayar'."\n";

        $result = $this->service()->validate($this->csvPath($csv));
        $this->assertFalse($result->hasErrors());

        $hasil = $this->service()->commit($result->validRows);

        $this->assertSame(2, $hasil['dibuat']);
        $this->assertSame(1, $hasil['pinjaman']);
        $this->assertDatabaseHas('loan_schedules', [
            'loan_id' => $loan->id,
            'installment_number' => 1,
            'principal_amount' => 350000.00,
            'interest_amount' => 49700.00,
            'total_amount' => 399700.00,
            'paid_amount' => 0.00,
            'status' => 'belum_bayar',
        ]);
    }

    /** Impor jadwal bukan peristiwa akuntansi — piutangnya sudah dijurnal saat pembukaan. */
    public function test_tidak_membuat_jurnal_apa_pun(): void
    {
        $this->actingAs($this->bendahara());
        $this->pinjaman('117-0151-00045');

        $csv = self::HEADER."\n".'117-0151-00045,1,2026-09-10,350000.00,49700.00,0,Belum Bayar'."\n";

        $result = $this->service()->validate($this->csvPath($csv));
        $this->service()->commit($result->validRows);

        $this->assertSame(0, JournalEntry::query()->count());
    }

    public function test_nomor_pinjaman_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->bendahara());

        $csv = self::HEADER."\n".'TIDAK-ADA,1,2026-09-10,350000.00,49700.00,0,Belum Bayar'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('tidak ada di daftar Pinjaman', implode(' ', $result->errors[0]['errors']));
    }

    /** Jadwal yang sudah terbentuk tidak boleh ditimpa diam-diam. */
    public function test_angsuran_yang_sudah_ada_ditolak_bukan_ditimpa(): void
    {
        $this->actingAs($this->bendahara());
        $loan = $this->pinjaman('117-0151-00045');

        LoanSchedule::query()->create([
            'loan_id' => $loan->id, 'installment_number' => 1, 'due_date' => '2026-01-01',
            'principal_amount' => 100, 'interest_amount' => 0, 'total_amount' => 100,
            'paid_amount' => 0, 'status' => 'belum_bayar',
        ]);

        $csv = self::HEADER."\n".'117-0151-00045,1,2026-09-10,350000.00,49700.00,0,Belum Bayar'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('sudah ada di jadwal', implode(' ', $result->errors[0]['errors']));
        $this->assertSame('100.00', LoanSchedule::query()->first()->total_amount);
    }

    public function test_duplikat_di_dalam_file_ditolak(): void
    {
        $this->actingAs($this->bendahara());
        $this->pinjaman('117-0151-00045');

        $csv = self::HEADER."\n"
            .'117-0151-00045,1,2026-09-10,350000.00,49700.00,0,Belum Bayar'."\n"
            .'117-0151-00045,1,2026-10-10,350000.00,49700.00,0,Belum Bayar'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertCount(1, $result->validRows);
        $this->assertStringContainsString('duplikat', implode(' ', $result->errors[0]['errors']));
    }

    /** Tanpa nominal terbayar, "Sebagian" akan mencatat sisa tagihan yang salah. */
    public function test_status_sebagian_wajib_menyertakan_nominal_dibayar(): void
    {
        $this->actingAs($this->bendahara());
        $this->pinjaman('117-0151-00045');

        $csv = self::HEADER."\n".'117-0151-00045,1,2026-09-10,350000.00,49700.00,0,Sebagian'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('nominal_dibayar', implode(' ', $result->errors[0]['errors']));
    }

    public function test_status_lunas_menandai_terbayar_penuh(): void
    {
        $this->actingAs($this->bendahara());
        $this->pinjaman('117-0151-00045');

        $csv = self::HEADER."\n".'117-0151-00045,1,2026-09-10,350000.00,49700.00,0,Lunas'."\n";

        $result = $this->service()->validate($this->csvPath($csv));
        $this->service()->commit($result->validRows);

        $jadwal = LoanSchedule::query()->firstOrFail();
        $this->assertSame('lunas', $jadwal->status);
        $this->assertSame('399700.00', $jadwal->paid_amount);
    }

    public function test_tanggal_tidak_sah_ditolak(): void
    {
        $this->actingAs($this->bendahara());
        $this->pinjaman('117-0151-00045');

        $csv = self::HEADER."\n".'117-0151-00045,1,31/02/2026,350000.00,49700.00,0,Belum Bayar'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('tanggal_jatuh_tempo', implode(' ', $result->errors[0]['errors']));
    }

    public function test_halaman_import_hanya_untuk_yang_berwenang(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $anggota = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $anggota->assignRole('anggota');

        $this->actingAs($anggota)
            ->get(route('admin.pinjaman.jadwal.import.form'))
            ->assertForbidden();

        $this->actingAs($this->bendahara())
            ->get(route('admin.pinjaman.jadwal.import.form'))
            ->assertOk();
    }

    /** All-or-nothing: satu baris gagal berarti tidak ada yang tersimpan. */
    public function test_mode_all_or_nothing_membatalkan_seluruhnya(): void
    {
        $user = $this->bendahara();
        $this->actingAs($user);
        $this->pinjaman('117-0151-00045');

        $csv = self::HEADER."\n"
            .'117-0151-00045,1,2026-09-10,350000.00,49700.00,0,Belum Bayar'."\n"
            .'TIDAK-ADA,1,2026-09-10,350000.00,49700.00,0,Belum Bayar'."\n";

        $this->post(route('admin.pinjaman.jadwal.import'), [
            'file' => UploadedFile::fake()->createWithContent('jadwal.csv', $csv),
            'mode' => 'all_or_nothing',
        ])->assertRedirect(route('admin.pinjaman.jadwal.import.form'));

        $this->assertSame(0, LoanSchedule::query()->count());
    }

    /** Partial: baris yang sah tetap masuk, yang gagal dilaporkan. */
    public function test_mode_partial_menyimpan_baris_yang_sah(): void
    {
        $user = $this->bendahara();
        $this->actingAs($user);
        $this->pinjaman('117-0151-00045');

        $csv = self::HEADER."\n"
            .'117-0151-00045,1,2026-09-10,350000.00,49700.00,0,Belum Bayar'."\n"
            .'TIDAK-ADA,1,2026-09-10,350000.00,49700.00,0,Belum Bayar'."\n";

        $this->post(route('admin.pinjaman.jadwal.import'), [
            'file' => UploadedFile::fake()->createWithContent('jadwal.csv', $csv),
            'mode' => 'partial',
        ])->assertSessionHas('status');

        $this->assertSame(1, LoanSchedule::query()->count());
    }
}
