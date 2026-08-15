<?php

namespace Tests\Feature\Loan;

use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Loan\LoanRepaymentHistoryImportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Import riwayat pembayaran angsuran dari sistem lama.
 *
 * Sifat yang menentukan seluruh rancangan: baris-baris ini ARSIP, bukan
 * transaksi. Saldo awal sudah memuat posisi akhir piutang, jadi import ini
 * tidak boleh menjurnal, tidak boleh mengubah sisa pokok, dan tidak boleh
 * menyentuh jadwal angsuran — kalau tidak, neraca yang baru diseimbangkan
 * akan bergerak dua kali.
 */
class LoanRepaymentHistoryImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'no_pinjaman_lama,tanggal_bayar,nominal_pokok,nominal_jasa,saldo_setelah,keterangan';

    private function bendahara(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('bendahara');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function service(): LoanRepaymentHistoryImportService
    {
        return app(LoanRepaymentHistoryImportService::class);
    }

    private function csvPath(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'riwayat_').'.csv';
        file_put_contents($path, $content);

        return $path;
    }

    public function test_riwayat_tersimpan_dengan_tanggal_bayar_asli(): void
    {
        $user = $this->bendahara();
        $this->actingAs($user);
        $loan = Loan::factory()->create(['loan_number' => '117-0151-00045']);

        $csv = self::HEADER."\n"
            .'117-0151-00045,2024-06-12,350000.00,49700.00,23155200.00,angsuran usp'."\n";

        $result = $this->service()->validate($this->csvPath($csv));
        $this->assertFalse($result->hasErrors());

        $hasil = $this->service()->commit($result->validRows, $user->id);

        $this->assertSame(1, $hasil['dibuat']);

        $baris = LoanRepayment::query()->firstOrFail();
        $this->assertSame($loan->id, $baris->loan_id);
        $this->assertSame('399700.00', $baris->amount);
        $this->assertSame('350000.00', $baris->principal_portion);
        $this->assertSame('49700.00', $baris->interest_portion);
        $this->assertSame('2024-06-12', $baris->paid_at->format('Y-m-d'));
    }

    /** Tanpa penanda, baris migrasi tidak bisa dibedakan dari transaksi asli. */
    public function test_baris_ditandai_sebagai_hasil_migrasi(): void
    {
        $user = $this->bendahara();
        $this->actingAs($user);
        Loan::factory()->create(['loan_number' => '117-0151-00045']);

        $csv = self::HEADER."\n".'117-0151-00045,2024-06-12,350000.00,49700.00,23155200.00,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));
        $this->service()->commit($result->validRows, $user->id);

        $this->assertTrue(LoanRepayment::query()->firstOrFail()->isMigrated());
    }

    /** Saldo awal sudah memuat posisi akhir — menjurnal ulang menggandakannya. */
    public function test_tidak_membuat_jurnal(): void
    {
        $user = $this->bendahara();
        $this->actingAs($user);
        Loan::factory()->create(['loan_number' => '117-0151-00045']);

        $csv = self::HEADER."\n".'117-0151-00045,2024-06-12,350000.00,49700.00,23155200.00,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));
        $this->service()->commit($result->validRows, $user->id);

        $this->assertSame(0, JournalEntry::query()->count());
        $this->assertNull(LoanRepayment::query()->firstOrFail()->journal_entry_id);
    }

    /** Jadwal angsuran sudah mencerminkan posisi setelah pembayaran ini. */
    public function test_tidak_mengubah_jadwal_angsuran_maupun_status_pinjaman(): void
    {
        $user = $this->bendahara();
        $this->actingAs($user);
        $loan = Loan::factory()->create(['loan_number' => '117-0151-00045', 'status' => 'dicairkan']);

        LoanSchedule::query()->create([
            'loan_id' => $loan->id, 'installment_number' => 1, 'due_date' => '2026-09-10',
            'principal_amount' => 350000, 'interest_amount' => 49700, 'total_amount' => 399700,
            'paid_amount' => 0, 'status' => 'belum_bayar',
        ]);

        $csv = self::HEADER."\n".'117-0151-00045,2024-06-12,350000.00,49700.00,23155200.00,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));
        $this->service()->commit($result->validRows, $user->id);

        $jadwal = LoanSchedule::query()->firstOrFail();
        $this->assertSame('0.00', $jadwal->paid_amount);
        $this->assertSame('belum_bayar', $jadwal->status);
        $this->assertSame('dicairkan', $loan->fresh()->status);
    }

    public function test_nomor_pinjaman_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->bendahara());

        $csv = self::HEADER."\n".'TIDAK-ADA,2024-06-12,350000.00,49700.00,0,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('tidak ada di daftar Pinjaman', implode(' ', $result->errors[0]['errors']));
    }

    public function test_tanggal_tidak_sah_ditolak(): void
    {
        $this->actingAs($this->bendahara());
        Loan::factory()->create(['loan_number' => '117-0151-00045']);

        $csv = self::HEADER."\n".'117-0151-00045,31/02/2024,350000.00,49700.00,0,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('tanggal_bayar', implode(' ', $result->errors[0]['errors']));
    }

    /** Riwayat boleh berulang untuk pinjaman yang sama — itu memang wajar. */
    public function test_banyak_pembayaran_untuk_satu_pinjaman_diterima(): void
    {
        $user = $this->bendahara();
        $this->actingAs($user);
        Loan::factory()->create(['loan_number' => '117-0151-00045']);

        $csv = self::HEADER."\n"
            .'117-0151-00045,2024-06-12,350000.00,49700.00,23155200.00,'."\n"
            .'117-0151-00045,2024-07-04,1300000.00,97300.00,21757900.00,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));
        $hasil = $this->service()->commit($result->validRows, $user->id);

        $this->assertSame(2, $hasil['dibuat']);
        $this->assertSame(1, $hasil['pinjaman']);
    }

    public function test_halaman_import_hanya_untuk_yang_berwenang(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $anggota = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $anggota->assignRole('anggota');

        $this->actingAs($anggota)
            ->get(route('admin.pinjaman.historis.import.form'))
            ->assertForbidden();

        $this->actingAs($this->bendahara())
            ->get(route('admin.pinjaman.historis.import.form'))
            ->assertOk();
    }

    public function test_mode_all_or_nothing_membatalkan_seluruhnya(): void
    {
        $this->actingAs($this->bendahara());
        Loan::factory()->create(['loan_number' => '117-0151-00045']);

        $csv = self::HEADER."\n"
            .'117-0151-00045,2024-06-12,350000.00,49700.00,23155200.00,'."\n"
            .'TIDAK-ADA,2024-06-12,350000.00,49700.00,0,'."\n";

        $this->post(route('admin.pinjaman.historis.import'), [
            'file' => UploadedFile::fake()->createWithContent('riwayat.csv', $csv),
            'mode' => 'all_or_nothing',
        ])->assertRedirect(route('admin.pinjaman.historis.import.form'));

        $this->assertSame(0, LoanRepayment::query()->count());
    }

    /** Laporan migrasi hanya menampilkan baris bertanda migrasi. */
    public function test_laporan_migrasi_memisahkan_riwayat_dari_pembayaran_berjalan(): void
    {
        $user = $this->bendahara();
        $this->actingAs($user);
        $loan = Loan::factory()->create(['loan_number' => '117-0151-00045']);

        $csv = self::HEADER."\n".'117-0151-00045,2024-06-12,350000.00,49700.00,23155200.00,'."\n";
        $result = $this->service()->validate($this->csvPath($csv));
        $this->service()->commit($result->validRows, $user->id);

        LoanRepayment::query()->create([
            'branch_id' => $loan->branch_id, 'loan_id' => $loan->id,
            'amount' => 100, 'principal_portion' => 100, 'interest_portion' => 0,
            'balance_after' => 0, 'created_by' => $user->id,
        ]);

        $controller = app(\App\Http\Controllers\Admin\LaporanController::class);
        $rows = (new \ReflectionMethod($controller, 'fetch'))->invoke($controller, 'migrasi_historis_pembayaran');

        $this->assertCount(1, $rows);
        $this->assertSame('12-06-2024', $rows->first()['tanggal']);
    }
}
