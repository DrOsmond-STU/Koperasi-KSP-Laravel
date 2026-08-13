<?php

namespace Tests\Feature\Accounting;

use App\Models\ChartOfAccount;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Accounting\ChartOfAccountImportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Import massal Bagan Akun — untuk membawa bagan akun koperasi yang sudah
 * berjalan (262 akun SMIK) tanpa mengetiknya satu per satu.
 *
 * Sifat yang dijaga: import bersifat TAMBAH/PERBARUI per kode, bukan
 * mengganti isi tabel. ChartOfAccountsSeeder menghapus seluruh baris sebelum
 * mengisi — memakai pola itu di sini akan memutus foreign key produk
 * simpanan/pinjaman dan melumpuhkan enam kode inti yang di-hardcode di
 * sejumlah service.
 */
class ChartOfAccountImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'code,name,type,group,normal_balance,is_postable,parent_code,statement,notes';

    /** Bagan Akun adalah wewenang admin_sistem (RolePermissionSeeder). */
    private function adminSistem(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('admin_sistem');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function csvPath(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'coa_').'.csv';
        file_put_contents($path, $content);

        return $path;
    }

    private function service(): ChartOfAccountImportService
    {
        return app(ChartOfAccountImportService::class);
    }

    public function test_akun_baru_dibuat_dengan_hierarki_utuh(): void
    {
        $csv = self::HEADER."\n"
            .'11000,ASET LANCAR,ASET,,DEBIT,FALSE,,NERACA,induk'."\n"
            .'11010,KAS DAN SETARA KAS,ASET,,DEBIT,FALSE,11000,NERACA,sub-induk'."\n"
            .'1101100,KAS KECIL (KSP),ASET,,DEBIT,TRUE,11010,NERACA,transaksi'."\n";

        $result = $this->service()->validate($this->csvPath($csv));
        $this->assertFalse($result->hasErrors());

        $hasil = $this->service()->commit($result->validRows);

        $this->assertSame(3, $hasil['dibuat']);
        $this->assertDatabaseHas('chart_of_accounts', [
            'code' => '1101100',
            'name' => 'KAS KECIL (KSP)',
            'is_postable' => true,
            'parent_code' => '11010',
            'statement' => 'NERACA',
        ]);
        $this->assertDatabaseHas('chart_of_accounts', ['code' => '11000', 'is_postable' => false]);
    }

    /** Import tidak boleh menghapus bagan akun yang sudah ada. */
    public function test_bagan_akun_yang_sudah_ada_tidak_terhapus(): void
    {
        $lama = ChartOfAccount::query()->create([
            'code' => '9999', 'name' => 'Akun Lama', 'type' => 'ASET',
            'normal_balance' => 'DEBIT', 'is_postable' => true, 'statement' => 'NERACA',
        ]);

        $csv = self::HEADER."\n".'11000,ASET LANCAR,ASET,,DEBIT,FALSE,,NERACA,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));
        $this->service()->commit($result->validRows);

        $this->assertDatabaseHas('chart_of_accounts', ['id' => $lama->id, 'code' => '9999']);
    }

    /** Kode yang sudah ada diperbarui, bukan digandakan. */
    public function test_kode_yang_sudah_ada_diperbarui(): void
    {
        ChartOfAccount::query()->create([
            'code' => '11000', 'name' => 'Nama Lama', 'type' => 'ASET',
            'normal_balance' => 'DEBIT', 'is_postable' => false, 'statement' => 'NERACA',
        ]);

        $csv = self::HEADER."\n".'11000,ASET LANCAR,ASET,,DEBIT,FALSE,,NERACA,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));
        $hasil = $this->service()->commit($result->validRows);

        $this->assertSame(0, $hasil['dibuat']);
        $this->assertSame(1, $hasil['diperbarui']);
        $this->assertSame(1, ChartOfAccount::query()->where('code', '11000')->count());
        $this->assertDatabaseHas('chart_of_accounts', ['code' => '11000', 'name' => 'ASET LANCAR']);
    }

    /** Enam kode inti di-hardcode di service; menimpanya lewat import ditolak. */
    public function test_akun_inti_tidak_boleh_ditimpa(): void
    {
        $kodeInti = ChartOfAccount::PROTECTED_CODES[0];

        $csv = self::HEADER."\n".$kodeInti.',Diganti Sembarangan,ASET,,DEBIT,TRUE,,NERACA,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('akun inti', implode(' ', $result->errors[0]['errors']));
    }

    public function test_parent_code_tidak_dikenal_ditolak(): void
    {
        $csv = self::HEADER."\n".'11000,ASET LANCAR,ASET,,DEBIT,FALSE,TIDAK-ADA,NERACA,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('parent_code', implode(' ', $result->errors[0]['errors']));
    }

    /** parent_code boleh menunjuk akun yang sudah ada di database. */
    public function test_parent_code_boleh_menunjuk_akun_yang_sudah_ada(): void
    {
        ChartOfAccount::query()->create([
            'code' => '1000', 'name' => 'ASET', 'type' => 'ASET',
            'normal_balance' => 'DEBIT', 'is_postable' => false, 'statement' => 'NERACA',
        ]);

        $csv = self::HEADER."\n".'11000,ASET LANCAR,ASET,,DEBIT,FALSE,1000,NERACA,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertFalse($result->hasErrors());
    }

    public function test_type_dan_normal_balance_tidak_sah_ditolak(): void
    {
        $csv = self::HEADER."\n".'11000,Ngawur,NGAWUR,,MIRING,FALSE,,NERACA,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $pesan = implode(' | ', $result->errors[0]['errors']);
        $this->assertStringContainsString('type harus', $pesan);
        $this->assertStringContainsString('normal_balance', $pesan);
    }

    public function test_kode_duplikat_di_dalam_file_ditolak(): void
    {
        $csv = self::HEADER."\n"
            .'11000,Pertama,ASET,,DEBIT,FALSE,,NERACA,'."\n"
            .'11000,Kedua,ASET,,DEBIT,FALSE,,NERACA,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertCount(1, $result->validRows);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('duplikat', implode(' ', $result->errors[0]['errors']));
    }

    public function test_halaman_import_hanya_untuk_yang_berwenang(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $anggota = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $anggota->assignRole('anggota');

        $this->actingAs($anggota)
            ->get(route('admin.master.chart-of-accounts.import.form'))
            ->assertForbidden();

        $this->actingAs($this->adminSistem())
            ->get(route('admin.master.chart-of-accounts.import.form'))
            ->assertOk();
    }
}
