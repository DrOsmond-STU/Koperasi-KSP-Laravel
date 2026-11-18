<?php

namespace Tests\Feature\OpeningBalance;

use App\Http\Controllers\Admin\OpeningBalanceNeracaCorrectionController;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceCoa;
use App\Models\OpeningBalanceCorrection;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\OpeningBalance\OpeningBalanceLockService;
use App\Services\OpeningBalance\OpeningBalanceNeracaCorrectionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Koreksi Saldo Awal Neraca: saldo awal migrasi & jurnal pembukaan tidak
 * berubah; koreksi = jurnal baru bertanggal cut-off yang bisa dibatalkan.
 */
class OpeningBalanceNeracaCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $bendahara;

    private OpeningBalanceBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->bendahara = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $this->bendahara->assignRole('bendahara');
        UserBranchScope::query()->create(['user_id' => $this->bendahara->id, 'scope_type' => 'all']);

        // Saldo awal: Kas 1.000.000 / Simpanan Sukarela 600.000 / Simpanan Pokok 400.000, lalu dikunci.
        $this->batch = OpeningBalanceBatch::query()->create([
            'branch_id' => Branch::factory()->create()->id,
            'cutoff_date' => '2026-07-31',
            'status' => 'draft',
        ]);
        foreach ([['1101', 'debit', 1000000], ['2101', 'kredit', 600000], ['3101', 'kredit', 400000]] as [$kode, $posisi, $jumlah]) {
            OpeningBalanceCoa::query()->create([
                'opening_balance_batch_id' => $this->batch->id,
                'chart_of_account_id' => $this->akun($kode)->id,
                'position' => $posisi,
                'amount' => $jumlah,
            ]);
        }
        app(OpeningBalanceLockService::class)->lock($this->batch, $this->bendahara);
        $this->batch->refresh();
    }

    private function akun(string $kode): ChartOfAccount
    {
        return ChartOfAccount::query()->where('code', $kode)->firstOrFail();
    }

    private function service(): OpeningBalanceNeracaCorrectionService
    {
        return app(OpeningBalanceNeracaCorrectionService::class);
    }

    private function sidikData(): array
    {
        return [
            'coa' => OpeningBalanceCoa::query()->orderBy('id')->get(['id', 'chart_of_account_id', 'position', 'amount', 'updated_at'])->toArray(),
            'jurnal_pembukaan' => JournalEntry::query()->where('source_type', OpeningBalanceBatch::class)->with('lines')->get()->toArray(),
        ];
    }

    public function test_koreksi_memposting_jurnal_baru_bertanggal_cutoff_tanpa_mengubah_data_lama(): void
    {
        $sebelum = $this->sidikData();
        $jumlahJurnal = JournalEntry::query()->count();

        // Kas seharusnya 1.050.000 dan Simpanan Sukarela 650.000.
        $koreksi = $this->service()->simpan($this->batch, [
            $this->akun('1101')->id => '1050000',
            $this->akun('2101')->id => '650000',
        ], null, 'Sesuaikan dengan neraca SMIK', $this->bendahara);

        $this->assertSame($sebelum, $this->sidikData(), 'Saldo awal migrasi & jurnal pembukaan tidak boleh berubah.');
        $this->assertSame($jumlahJurnal + 1, JournalEntry::query()->count());

        $jurnal = JournalEntry::query()->with('lines')->findOrFail($koreksi->journal_entry_id);
        $this->assertSame('2026-07-31', $jurnal->entry_date->toDateString());
        $this->assertSame(OpeningBalanceCorrection::class, $jurnal->source_type);
        $this->assertEqualsWithDelta(50000, (float) $jurnal->lines->firstWhere('chart_of_account_id', $this->akun('1101')->id)->debit, 0.001);
        $this->assertEqualsWithDelta(50000, (float) $jurnal->lines->firstWhere('chart_of_account_id', $this->akun('2101')->id)->credit, 0.001);

        // Buku besar / neraca per cut-off memakai saldo yang sudah dikoreksi.
        $ledger = app(GeneralLedgerService::class);
        $this->assertSame('1050000.00', $ledger->balanceFor($this->akun('1101'), null, '2026-07-31'));
        $this->assertSame('650000.00', $ledger->balanceFor($this->akun('2101'), null, '2026-08-31'));
        $this->assertSame('0.00', $ledger->balanceFor($this->akun('1101'), null, '2026-07-30'));

        $posisi = $this->service()->posisi($this->batch);
        $kas = $posisi['baris']->first(fn ($b) => $b['account']->code === '1101');
        $this->assertEqualsWithDelta(1000000, $kas['asal'], 0.001);
        $this->assertEqualsWithDelta(50000, $kas['koreksi'], 0.001);
        $this->assertEqualsWithDelta(1050000, $kas['sekarang'], 0.001);
        $this->assertEqualsWithDelta(0, $posisi['ringkasan']['selisih'], 0.001);
    }

    public function test_akun_penyeimbang_menutup_selisih_dan_koreksi_tidak_seimbang_ditolak(): void
    {
        try {
            $this->service()->simpan($this->batch, [$this->akun('1101')->id => '1100000'], null, 'uji', $this->bendahara);
            $this->fail('Koreksi tidak seimbang seharusnya ditolak');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('belum seimbang', $e->getMessage());
        }
        $this->assertSame(0, OpeningBalanceCorrection::query()->count());

        $rencana = $this->service()->rencana($this->batch, [$this->akun('1101')->id => '1100000'], $this->akun('3102')->id);
        $this->assertEqualsWithDelta(0, $rencana['selisih'], 0.001);
        $penyeimbang = collect($rencana['baris'])->firstWhere('penyeimbang', true);
        $this->assertSame('3102', $penyeimbang['account']->code);
        $this->assertEqualsWithDelta(100000, $penyeimbang['kredit'], 0.001);
        $this->assertEqualsWithDelta(0, $rencana['sesudah']['selisih'], 0.001);
    }

    public function test_akun_shu_berjalan_tidak_bisa_dikoreksi_langsung(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SHU Tahun Berjalan');

        $this->service()->rencana($this->batch, [$this->akun('3150')->id => '5000'], $this->akun('3102')->id);
    }

    public function test_batalkan_koreksi_membalik_jurnal_dan_saldo_kembali(): void
    {
        $koreksi = $this->service()->simpan($this->batch, [
            $this->akun('1101')->id => '1050000',
            $this->akun('2101')->id => '650000',
        ], null, 'uji', $this->bendahara);

        $batal = $this->service()->batalkan($koreksi, 'salah input', $this->bendahara);

        $this->assertTrue($batal->isCancelled());
        $pembalik = JournalEntry::query()->findOrFail($batal->reversal_journal_entry_id);
        $this->assertSame($koreksi->journal_entry_id, (int) $pembalik->reversal_of_entry_id);
        $this->assertSame('1000000.00', app(GeneralLedgerService::class)->balanceFor($this->akun('1101'), null, '2026-08-31'));
        $this->assertSame([], $this->service()->koreksiPerAkun([$this->batch->id]));

        $this->expectException(RuntimeException::class);
        $this->service()->batalkan($batal, 'lagi', $this->bendahara);
    }

    public function test_laporan_saldo_awal_ikut_memakai_koreksi(): void
    {
        $this->service()->simpan($this->batch, [
            $this->akun('1101')->id => '1050000',
            $this->akun('2101')->id => '650000',
        ], null, 'uji', $this->bendahara);

        $gabung = $this->service()->gabungKoreksi(OpeningBalanceCoa::query()->with('account')->get(), [$this->batch->id]);
        $kas = $gabung->first(fn ($l) => $l->account->code === '1101');
        $this->assertSame('debit', $kas->position);
        $this->assertEqualsWithDelta(1050000, (float) $kas->amount, 0.001);
        $this->assertFalse($kas->exists, 'Baris gabungan tidak boleh disimpan ke database.');
        $this->assertEqualsWithDelta(
            (float) $gabung->where('position', 'debit')->sum('amount'),
            (float) $gabung->where('position', 'kredit')->sum('amount'),
            0.001,
        );
        $this->assertSame(3, OpeningBalanceCoa::query()->count());
    }

    public function test_alur_layar_pratinjau_tidak_menyimpan_lalu_simpan_dengan_kata_sandi(): void
    {
        $isian = [
            'target' => [$this->akun('1101')->id => '1.050.000', $this->akun('2101')->id => '650.000,00'],
            'alasan' => 'Sesuaikan neraca SMIK',
        ];

        $this->actingAs($this->bendahara)->get(route('admin.saldo-awal.koreksi-neraca.pilih'))
            ->assertRedirect(route('admin.saldo-awal.koreksi-neraca', $this->batch));
        $this->actingAs($this->bendahara)->get(route('admin.saldo-awal.koreksi-neraca', $this->batch))
            ->assertOk()->assertSee('Koreksi Saldo Awal Neraca')->assertSee('Simpanan Sukarela Anggota');

        $this->actingAs($this->bendahara)->post(route('admin.saldo-awal.koreksi-neraca.pratinjau', $this->batch), $isian)
            ->assertOk()->assertSee('Pratinjau Koreksi Saldo Awal Neraca')->assertSee('1.050.000,00')->assertSee('Simpan Koreksi');
        $this->assertSame(0, OpeningBalanceCorrection::query()->count());

        $this->actingAs($this->bendahara)->post(route('admin.saldo-awal.koreksi-neraca.simpan', $this->batch), $isian + ['konfirmasi' => 1, 'password' => 'salah'])
            ->assertRedirect(route('admin.saldo-awal.koreksi-neraca', $this->batch))->assertSessionHas('error');
        $this->assertSame(0, OpeningBalanceCorrection::query()->count());

        $kunci = '6f1e7c2a-1111-4c3b-9d1e-2a3b4c5d6e7f';
        foreach ([1, 2] as $_) {
            $this->actingAs($this->bendahara)->post(route('admin.saldo-awal.koreksi-neraca.simpan', $this->batch), $isian + ['konfirmasi' => 1, 'password' => 'password', 'idempotency_key' => $kunci])
                ->assertRedirect(route('admin.saldo-awal.koreksi-neraca', $this->batch))->assertSessionHas('status');
        }
        $this->assertSame(1, OpeningBalanceCorrection::query()->count(), 'Klik ganda tidak boleh membuat koreksi dobel.');

        $this->actingAs($this->bendahara)->get(route('admin.saldo-awal.koreksi-neraca', $this->batch))
            ->assertOk()->assertSee('Koreksi #'.OpeningBalanceCorrection::query()->value('id'))->assertSee('Batalkan koreksi');

        $t = DB::selectOne('SELECT ROUND(SUM(debit),2) d, ROUND(SUM(credit),2) k FROM journal_lines');
        $this->assertSame((string) $t->d, (string) $t->k);
    }

    public function test_halaman_laporan_saldo_awal_neraca_menampilkan_saldo_terkoreksi(): void
    {
        $this->service()->simpan($this->batch, [
            $this->akun('1101')->id => '1050000',
            $this->akun('2101')->id => '650000',
        ], null, 'uji', $this->bendahara);

        foreach (['saldo_awal_neraca', 'saldo_awal_neraca_saldo'] as $modul) {
            $this->actingAs($this->bendahara)->get(route('admin.laporan.show', $modul))
                ->assertOk()->assertSee('1.050.000')->assertSee('650.000');
        }
        $this->actingAs($this->bendahara)->get(route('admin.laporan.saldo-awal-neraca.print-scontro'))->assertOk();
    }

    public function test_peran_tanpa_hak_saldo_awal_update_ditolak(): void
    {
        $teller = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $teller->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $teller->id, 'scope_type' => 'all']);

        $this->actingAs($teller)->get(route('admin.saldo-awal.koreksi-neraca', $this->batch))->assertForbidden();
        $this->actingAs($teller)->post(route('admin.saldo-awal.koreksi-neraca.simpan', $this->batch), [
            'target' => [$this->akun('1101')->id => '1'], 'alasan' => 'x', 'konfirmasi' => 1, 'password' => 'password',
        ])->assertForbidden();
        $this->assertSame(0, OpeningBalanceCorrection::query()->count());
    }

    public function test_format_angka_indonesia_dibaca_benar(): void
    {
        $this->assertSame('1250000', OpeningBalanceNeracaCorrectionController::angka('1.250.000'));
        $this->assertSame('1250000.50', OpeningBalanceNeracaCorrectionController::angka('Rp 1.250.000,50'));
        $this->assertSame('1250000.5', OpeningBalanceNeracaCorrectionController::angka('1250000.5'));
        $this->assertSame('-30000', OpeningBalanceNeracaCorrectionController::angka('-30.000'));
        $this->assertNull(OpeningBalanceNeracaCorrectionController::angka('  '));
    }
}
