<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Accounting\JournalEngine;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Tests\TestCase;

/**
 * Ekspor Excel & PDF halaman Buku Besar: isi sama dengan layar (akun,
 * cabang, periode, jurnal dibatalkan disembunyikan/ditampilkan).
 */
class GeneralLedgerExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $branch;

    private ChartOfAccount $kas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->branch = Branch::query()->create(['code' => 'CBG-01', 'name' => 'Cabang Utama', 'is_active' => true]);
        $this->user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $this->user->assignRole('bendahara');
        UserBranchScope::query()->create(['user_id' => $this->user->id, 'scope_type' => 'all']);
        $this->kas = ChartOfAccount::query()->where('code', '1101')->firstOrFail();

        $engine = app(JournalEngine::class);
        $post = fn (string $tgl, string $ket, float $n, string $lawan = '4101') => $engine->post([
            'branch_id' => $this->branch->id, 'entry_date' => $tgl, 'description' => $ket, 'created_by' => $this->user->id,
            'lines' => [
                ['account_code' => '1101', 'debit' => $n, 'credit' => 0],
                ['account_code' => $lawan, 'debit' => 0, 'credit' => $n],
            ],
        ]);
        $post('2026-10-31', 'Sebelum periode', 1000000);
        $post('2026-11-02', 'Terima jasa pinjaman', 250000);
        $batal = $post('2026-11-05', 'Salah input dobel', 777000);
        $engine->reverse($batal, 'dobel', $this->user->id);
        $post('2026-11-10', 'Terima jasa pinjaman 2', 150000.5);
    }

    private function filterUji(array $tambah = []): array
    {
        return ['chart_of_account_id' => $this->kas->id, 'branch_id' => $this->branch->id, 'period_start' => '2026-11-01', 'period_end' => '2026-11-30'] + $tambah;
    }

    public function test_halaman_menampilkan_tombol_ekspor_bila_akun_dipilih(): void
    {
        $this->actingAs($this->user)->get(route('admin.jurnal-buku-besar.index', $this->filterUji()))
            ->assertOk()->assertSee('Ekspor Excel')->assertSee('Ekspor PDF');
        $this->actingAs($this->user)->get(route('admin.jurnal-buku-besar.index'))
            ->assertOk()->assertDontSee('Ekspor Excel');
    }

    public function test_ekspor_excel_berisi_saldo_awal_mutasi_dan_total(): void
    {
        $res = $this->actingAs($this->user)->get(route('admin.jurnal-buku-besar.excel', $this->filterUji()));
        $res->assertOk();
        $this->assertStringContainsString('buku-besar-1101-2026-11-01-sd-2026-11-30.xlsx', $res->headers->get('content-disposition'));

        $sheet = IOFactory::load($res->baseResponse->getFile()->getPathname())->getActiveSheet();
        $baris = $sheet->toArray(null, true, false, false);

        $this->assertSame('BUKU BESAR', $baris[0][0]);
        $this->assertStringContainsString('1101', $baris[1][1]);
        $this->assertSame(['Tanggal', 'No. Jurnal', 'Keterangan', 'Debit', 'Kredit', 'Saldo Berjalan'], $baris[6]);
        // saldo awal periode
        $this->assertSame('Saldo Awal Periode', $baris[7][2]);
        $this->assertEqualsWithDelta(1000000, $baris[7][5], 0.001);
        // mutasi: jurnal dibatalkan disembunyikan bawaan
        $this->assertSame('Terima jasa pinjaman', $baris[8][2]);
        $this->assertSame('2026-11-02', ExcelDate::excelToDateTimeObject($baris[8][0])->format('Y-m-d'));
        $this->assertEqualsWithDelta(250000, $baris[8][3], 0.001);
        $this->assertEqualsWithDelta(1250000, $baris[8][5], 0.001);
        $this->assertSame('Terima jasa pinjaman 2', $baris[9][2]);
        $this->assertSame('TOTAL MUTASI', $baris[10][2]);
        $this->assertEqualsWithDelta(400000.5, $baris[10][3], 0.001);
        $this->assertEqualsWithDelta(1400000.5, $baris[10][5], 0.001);
        $this->assertCount(11, $baris);

        // dengan jurnal dibatalkan: asal + pembalik ikut, saldo akhir sama
        $res2 = $this->actingAs($this->user)->get(route('admin.jurnal-buku-besar.excel', $this->filterUji(['tampilkan_dibatalkan' => 1])));
        $baris2 = IOFactory::load($res2->baseResponse->getFile()->getPathname())->getActiveSheet()->toArray(null, true, false, false);
        $this->assertCount(13, $baris2);
        $this->assertEqualsWithDelta(1400000.5, end($baris2)[5], 0.001);
    }

    public function test_ekspor_pdf_mengunduh_berkas_pdf(): void
    {
        $res = $this->actingAs($this->user)->get(route('admin.jurnal-buku-besar.pdf', $this->filterUji()));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $this->assertStringContainsString('buku-besar-1101-2026-11-01-sd-2026-11-30.pdf', $res->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_tanpa_akun_dikembalikan_ke_halaman_dan_tanpa_hak_ditolak(): void
    {
        $this->actingAs($this->user)->get(route('admin.jurnal-buku-besar.excel'))
            ->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->user)->get(route('admin.jurnal-buku-besar.pdf'))
            ->assertRedirect()->assertSessionHas('error');

        $teller = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $teller->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $teller->id, 'scope_type' => 'all']);
        if (! $teller->can('jurnal.read')) {
            $this->actingAs($teller)->get(route('admin.jurnal-buku-besar.excel', $this->filterUji()))->assertForbidden();
            $this->actingAs($teller)->get(route('admin.jurnal-buku-besar.pdf', $this->filterUji()))->assertForbidden();
        }
    }
}
