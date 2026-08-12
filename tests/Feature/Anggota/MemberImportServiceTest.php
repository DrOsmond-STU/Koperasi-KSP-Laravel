<?php

namespace Tests\Feature\Anggota;

use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use App\Services\MemberImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Import massal Master Anggota — perilaku level validasi & commit, lepas
 * dari lapisan HTTP. Pola mengikuti OpeningBalanceImportServiceTest.
 */
class MemberImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'kode_anggota,nama,kode_cabang,kode_jenis_anggota,nik,alamat,telepon,email,tanggal_lahir,status,tanggal_bergabung';

    private function csvPath(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'anggota_').'.csv';
        file_put_contents($path, $content);

        return $path;
    }

    private function service(): MemberImportService
    {
        return app(MemberImportService::class);
    }

    /** Baris sah dipetakan ke kolom Member dengan benar. */
    public function test_valid_row_is_mapped_to_member_fields(): void
    {
        $branch = Branch::factory()->create(['code' => '001']);
        $type = MemberType::factory()->create(['code' => 'P']);

        $csv = self::HEADER."\n"
            .'1170100001,MUHATTAM,001,P,3276010101800001,"JL. ANGGREK NO 1, DEPOK",08123456789,budi@example.com,1980-01-01,Aktif,2023-08-01'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertFalse($result->hasErrors());
        $this->assertCount(1, $result->validRows);

        $data = $result->validRows[0]['data'];
        $this->assertSame($branch->id, $data['branch_id']);
        $this->assertSame($type->id, $data['member_type_id']);
        $this->assertSame('1170100001', $data['member_number']);
        $this->assertSame('MUHATTAM', $data['name']);
        $this->assertSame('JL. ANGGREK NO 1, DEPOK', $data['address']);
        $this->assertSame('aktif', $data['status']);
        $this->assertSame('1980-01-01', $data['date_of_birth']);
        $this->assertSame('2023-08-01', $data['joined_at']);
    }

    /** Kolom opsional yang kosong menjadi null, bukan string kosong. */
    public function test_blank_optional_columns_become_null(): void
    {
        Branch::factory()->create(['code' => '001']);
        MemberType::factory()->create(['code' => 'P']);

        $csv = self::HEADER."\n".'1170100002,EUIS,001,P,,,,,,Aktif,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertFalse($result->hasErrors());
        $data = $result->validRows[0]['data'];
        foreach (['nik', 'address', 'phone', 'email', 'date_of_birth', 'joined_at'] as $field) {
            $this->assertNull($data[$field], "{$field} seharusnya null");
        }
    }

    /** Kode cabang / jenis anggota yang tidak ada dilaporkan per baris. */
    public function test_unknown_master_codes_produce_row_errors(): void
    {
        $csv = self::HEADER."\n".'1170100003,SITI,TIDAK-ADA,JUGA-TIDAK-ADA,,,,,,Aktif,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertTrue($result->hasErrors());
        $this->assertCount(0, $result->validRows);
        $this->assertSame(2, $result->errors[0]['row']);

        $joined = implode(' | ', $result->errors[0]['errors']);
        $this->assertStringContainsString('kode_cabang', $joined);
        $this->assertStringContainsString('kode_jenis_anggota', $joined);
    }

    /** Nomor anggota yang sudah ada di database ditolak. */
    public function test_member_number_already_in_database_is_rejected(): void
    {
        Branch::factory()->create(['code' => '001']);
        MemberType::factory()->create(['code' => 'P']);
        Member::factory()->create(['member_number' => '1170100001']);

        $csv = self::HEADER."\n".'1170100001,KEMBAR,001,P,,,,,,Aktif,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('sudah terdaftar', implode(' ', $result->errors[0]['errors']));
    }

    /** Nomor anggota yang kembar di dalam file itu sendiri ditolak. */
    public function test_duplicate_member_number_within_file_is_rejected(): void
    {
        Branch::factory()->create(['code' => '001']);
        MemberType::factory()->create(['code' => 'P']);

        $csv = self::HEADER."\n"
            .'1170100001,PERTAMA,001,P,,,,,,Aktif,'."\n"
            .'1170100001,KEDUA,001,P,,,,,,Aktif,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertCount(1, $result->validRows);
        $this->assertCount(1, $result->errors);
        $this->assertSame(3, $result->errors[0]['row']);
        $this->assertStringContainsString('duplikat dengan baris 2', implode(' ', $result->errors[0]['errors']));
    }

    /** Status di luar daftar dan tanggal mustahil ditolak. */
    public function test_invalid_status_and_impossible_date_are_rejected(): void
    {
        Branch::factory()->create(['code' => '001']);
        MemberType::factory()->create(['code' => 'P']);

        $csv = self::HEADER."\n"
            .'1170100004,ANDI,001,P,,,,,2026-02-31,Ngawur,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertTrue($result->hasErrors());
        $joined = implode(' | ', $result->errors[0]['errors']);
        $this->assertStringContainsString('status harus salah satu', $joined);
        $this->assertStringContainsString('tanggal_lahir', $joined);
    }

    /** Email tidak sah ditolak, email sah diterima. */
    public function test_invalid_email_is_rejected(): void
    {
        Branch::factory()->create(['code' => '001']);
        MemberType::factory()->create(['code' => 'P']);

        $csv = self::HEADER."\n".'1170100005,RINA,001,P,,,,bukan-email,,Aktif,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('bukan alamat email yang sah', implode(' ', $result->errors[0]['errors']));
    }

    /**
     * commit() menyimpan lewat Eloquent sehingga kolom PII benar-benar
     * terenkripsi at-rest — inilah alasan import tidak boleh lewat SQL.
     */
    public function test_commit_persists_members_with_encrypted_pii(): void
    {
        Branch::factory()->create(['code' => '001']);
        MemberType::factory()->create(['code' => 'P']);

        $nik = '3276010101800001';
        $csv = self::HEADER."\n"
            ."1170100006,DEWI,001,P,{$nik},JL. MELATI,08120000000,dewi@example.com,1985-05-05,Aktif,2024-01-01\n";

        $result = $this->service()->validate($this->csvPath($csv));
        $committed = $this->service()->commit($result->validRows);

        $this->assertSame(1, $committed);

        $member = Member::query()->where('member_number', '1170100006')->firstOrFail();
        $this->assertSame($nik, $member->nik);
        $this->assertSame('dewi@example.com', $member->email);

        $rawNik = DB::table('members')->where('member_number', '1170100006')->value('nik');
        $this->assertNotSame($nik, $rawNik, 'nik seharusnya tersimpan terenkripsi, bukan plaintext');
        $this->assertNotSame('', (string) $rawNik);

        // name sengaja TIDAK dienkripsi (dipakai untuk pencarian sehari-hari)
        $this->assertSame('DEWI', DB::table('members')->where('member_number', '1170100006')->value('name'));
    }

    /**
     * Pengecekan duplikat harus melihat SELURUH anggota, bukan hanya cabang
     * yang boleh dilihat user. User tanpa baris UserBranchScope punya
     * allowedBranchIds() = [] sehingga BranchScope menyembunyikan semua
     * anggota — tanpa withoutGlobalScope, duplikat akan lolos di sini lalu
     * ditolak constraint unik database.
     */
    public function test_duplicate_check_ignores_branch_scope(): void
    {
        Branch::factory()->create(['code' => '001']);
        MemberType::factory()->create(['code' => 'P']);
        Member::factory()->create(['member_number' => '1170100007']);

        $this->actingAs(User::factory()->create());

        // Prasyarat: scope memang menyembunyikan anggota dari user ini.
        $this->assertSame(0, Member::query()->count());

        $csv = self::HEADER."\n".'1170100007,KEMBAR LINTAS CABANG,001,P,,,,,,Aktif,'."\n";

        $result = $this->service()->validate($this->csvPath($csv));

        $this->assertTrue($result->hasErrors(), 'duplikat lintas cabang harus tetap terdeteksi');
        $this->assertStringContainsString('sudah terdaftar', implode(' ', $result->errors[0]['errors']));
    }
}
