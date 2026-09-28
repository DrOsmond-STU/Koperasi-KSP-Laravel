<?php

namespace Tests\Feature\Loans;

use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\LoanScheduleAlignment;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceLoan;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Loans\LoanScheduleAlignmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Penyelarasan jadwal angsuran dengan buku besar.
 *
 * Pemindaian produksi 28 Sep 2026: 112 dari 161 pinjaman jadwalnya
 * menyimpang dari buku besar. Kasus-kasus di sini ditulis dari angka nyata
 * pemindaian itu (SUCIPTO, DORMAN, MULYA WATI) supaya alatnya terbukti
 * bekerja pada bentuk data yang benar-benar ada, bukan contoh rekaan.
 */
class LoanScheduleAlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function pengurus(string $role = 'bendahara'): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole($role);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    /** Batch saldo awal terkunci: pokok & jasa per cutoff untuk pinjaman ini. */
    private function saldoAwal(Loan $loan, float $pokok, float $jasa): void
    {
        $batch = OpeningBalanceBatch::query()->create([
            'branch_id' => Branch::factory()->create()->id,
            'cutoff_date' => '2026-07-31',
            'status' => 'locked',
        ]);

        OpeningBalanceLoan::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'member_id' => $loan->member_id,
            'loan_product_id' => $loan->loan_product_id,
            'external_loan_number' => $loan->loan_number,
            'disbursement_date' => '2025-12-11',
            'original_principal' => $loan->principal_amount,
            'outstanding_principal' => $pokok,
            'outstanding_interest' => $jasa,
            'tenor_days' => 200,
            'remaining_tenor_days' => 100,
            'next_installment_number' => 101,
            'next_due_date' => '2026-08-01',
            'collectibility' => 'lancar',
        ]);
    }

    /**
     * @param  callable(int): array{0: float, 1: float}  $terbayar  nomor angsuran => [pokok terbayar, jasa terbayar]
     */
    private function jadwal(Loan $loan, int $n, float $pokok, float $jasa, callable $terbayar): void
    {
        for ($i = 1; $i <= $n; $i++) {
            [$pp, $pi] = $terbayar($i);
            LoanSchedule::query()->create([
                'loan_id' => $loan->id,
                'installment_number' => $i,
                'due_date' => now()->addDays($i),
                'principal_amount' => $pokok,
                'interest_amount' => $jasa,
                'total_amount' => $pokok + $jasa,
                'paid_principal_amount' => $pp,
                'paid_interest_amount' => $pi,
                'paid_amount' => $pp + $pi,
                'status' => $pp + $pi >= $pokok + $jasa ? 'lunas' : ($pp + $pi > 0 ? 'sebagian' : 'belum_bayar'),
            ]);
        }
    }

    private function angsuran(Loan $loan, float $pokok, float $jasa, float $denda = 0, bool $dibatalkan = false): LoanRepayment
    {
        return LoanRepayment::query()->create([
            'branch_id' => $loan->branch_id,
            'loan_id' => $loan->id,
            'amount' => $pokok + $jasa + $denda,
            'principal_portion' => $pokok,
            'interest_portion' => $jasa,
            'penalty_portion' => $denda,
            'balance_after' => 0,
            'paid_at' => '2026-09-02',
            'created_by' => User::factory()->create()->id,
            'cancelled_at' => $dibatalkan ? now() : null,
        ]);
    }

    /** @return array{pokok: float, jasa: float} */
    private function sisaJadwal(Loan $loan): array
    {
        $j = LoanSchedule::query()->where('loan_id', $loan->id)->get();

        return [
            'pokok' => round((float) $j->sum('principal_amount') - (float) $j->sum('paid_principal_amount'), 2),
            'jasa' => round((float) $j->sum('interest_amount') - (float) $j->sum('paid_interest_amount'), 2),
        ];
    }

    /**
     * SUCIPTO (117-0151-00983): 200 angsuran @75.000+7.500, jadwal menyisakan
     * pokok 50.000 / jasa 0, tapi buku besar (saldo awal 8.622.500 + 862.500
     * dikurangi angsuran aplikasi 8.327.500 + 855.000) menyisakan 295.000 /
     * 7.500. Denda 50.000 pada angsurannya tidak boleh berpengaruh.
     */
    private function sucipto(): Loan
    {
        $loan = Loan::factory()->create(['status' => 'dicairkan', 'principal_amount' => 15000000]);
        $this->saldoAwal($loan, 8622500, 862500);
        $this->jadwal($loan, 200, 75000, 7500, fn (int $i) => $i < 200 ? [75000, 7500] : [25000, 7500]);
        $this->angsuran($loan, 8327500, 855000, 50000);

        return $loan->fresh();
    }

    public function test_rencana_mengikuti_buku_besar_bukan_jadwal(): void
    {
        $loan = $this->sucipto();

        $rencana = app(LoanScheduleAlignmentService::class)->temukan();

        $this->assertCount(1, $rencana);
        $r = $rencana->first();
        $this->assertSame($loan->id, $r['loan_id']);
        $this->assertTrue($r['migrasi']);
        $this->assertEquals(8622500, $r['pokok_awal']);
        $this->assertEquals(8327500, $r['pokok_bayar']);
        $this->assertEquals(295000, $r['target_sisa_pokok']);
        $this->assertEquals(7500, $r['target_sisa_jasa']);
        $this->assertEquals(50000, $r['sisa_pokok_jadwal']);
        $this->assertEquals(245000, $r['selisih_pokok']);
        $this->assertEquals(7500, $r['selisih_jasa']);
        $this->assertTrue($r['perlu']);
        $this->assertTrue($r['bisa']);
        $this->assertSame('dicairkan', $r['status_baru']);
    }

    public function test_jalankan_menyamakan_jadwal_dengan_buku_besar_tanpa_menyentuh_jurnal(): void
    {
        $loan = $this->sucipto();

        $hasil = app(LoanScheduleAlignmentService::class)->jalankan([$loan->id], $this->pengurus()->id);

        $this->assertSame(['pokok' => 295000.0, 'jasa' => 7500.0], $this->sisaJadwal($loan));
        $this->assertSame('dicairkan', $loan->fresh()->status);
        $this->assertSame(1, $hasil->loans_aligned);
        $this->assertGreaterThan(0, $hasil->rows_changed);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('loan_repayments', 1);

        // Terbayar disebar dari angsuran tertua: baris terakhir yang bersisa.
        $terakhir = LoanSchedule::query()->where('loan_id', $loan->id)->orderByDesc('installment_number')->first();
        $this->assertSame('belum_bayar', $terakhir->status);
        $this->assertEquals(0, $terakhir->paid_amount);

        // Sudah selaras: tidak muncul lagi di pemindaian berikutnya.
        $this->assertCount(0, app(LoanScheduleAlignmentService::class)->temukan());
    }

    /** DORMAN (117-0151-01050): ditandai lunas, padahal buku besar masih bersisa Rp 2.025.000. */
    public function test_pinjaman_lunas_yang_masih_bersisa_dibuka_kembali(): void
    {
        $loan = Loan::factory()->create(['status' => 'lunas', 'principal_amount' => 20000000]);
        $this->saldoAwal($loan, 13350000, 1335000);
        $this->jadwal($loan, 100, 133500, 13350, fn () => [133500, 13350]);
        $this->angsuran($loan, 11325000, 1335000);

        $service = app(LoanScheduleAlignmentService::class);
        $r = $service->temukan()->first();

        $this->assertSame('dicairkan', $r['status_baru']);
        $this->assertStringContainsString('DIBUKA KEMBALI', implode(' ', $r['peringatan']));

        $service->jalankan([$loan->id], $this->pengurus()->id);

        $this->assertSame('dicairkan', $loan->fresh()->status);
        $this->assertSame(['pokok' => 2025000.0, 'jasa' => 0.0], $this->sisaJadwal($loan));
    }

    public function test_pinjaman_yang_lunas_menurut_buku_besar_ditandai_lunas(): void
    {
        $loan = Loan::factory()->create(['status' => 'dicairkan', 'principal_amount' => 3000000]);
        $this->jadwal($loan, 3, 1000000, 100000, fn (int $i) => $i < 3 ? [1000000, 100000] : [0, 0]);
        $this->angsuran($loan, 3000000, 300000, 100000);

        app(LoanScheduleAlignmentService::class)->jalankan([$loan->id], $this->pengurus()->id);

        $this->assertSame('lunas', $loan->fresh()->status);
        $this->assertSame(['pokok' => 0.0, 'jasa' => 0.0], $this->sisaJadwal($loan));
    }

    /** MULYA WATI (MIGRASI-1229): jadwalnya cuma memuat sebagian kecil pokok — tidak bisa diselaraskan. */
    public function test_jadwal_yang_lebih_kecil_dari_sisa_buku_tidak_disentuh(): void
    {
        $loan = Loan::factory()->create(['status' => 'dicairkan', 'principal_amount' => 10000000]);
        $this->saldoAwal($loan, 8876615, 960000);
        $this->jadwal($loan, 10, 100000, 10000, fn () => [100000, 10000]);

        $service = app(LoanScheduleAlignmentService::class);
        $r = $service->temukan()->first();

        $this->assertFalse($r['bisa']);
        $this->assertStringContainsString('perlu dibangun ulang', implode(' ', $r['peringatan']));

        $this->expectException(RuntimeException::class);
        $service->jalankan([$loan->id], $this->pengurus()->id);
    }

    public function test_jasa_tidak_disentuh_bila_saldo_awal_tidak_mencatat_sisa_jasa(): void
    {
        $loan = Loan::factory()->create(['status' => 'dicairkan', 'principal_amount' => 3000000]);
        $this->saldoAwal($loan, 2000000, 0);
        $this->jadwal($loan, 3, 1000000, 100000, fn () => [0, 0]);
        $this->angsuran($loan, 1000000, 100000);

        $service = app(LoanScheduleAlignmentService::class);
        $r = $service->temukan()->first();
        $this->assertNull($r['sisa_jasa_buku']);

        $service->jalankan([$loan->id], $this->pengurus()->id);

        // Pokok mengikuti buku besar (2.000.000 - 1.000.000); jasa jadwal utuh, jadi belum lunas.
        $this->assertSame(['pokok' => 1000000.0, 'jasa' => 300000.0], $this->sisaJadwal($loan));
        $this->assertSame('dicairkan', $loan->fresh()->status);
    }

    public function test_pinjaman_aplikasi_memakai_plafon_dan_jasa_jadwal_sebagai_awal(): void
    {
        $loan = Loan::factory()->create(['status' => 'dicairkan', 'principal_amount' => 3000000]);
        $this->jadwal($loan, 3, 1000000, 100000, fn () => [0, 0]);
        $this->angsuran($loan, 2000000, 200000);
        $this->angsuran($loan, 1000000, 100000, dibatalkan: true);

        $service = app(LoanScheduleAlignmentService::class);
        $r = $service->temukan()->first();

        $this->assertFalse($r['migrasi']);
        $this->assertEquals(3000000, $r['pokok_awal']);
        $this->assertEquals(300000, $r['jasa_awal']);
        $this->assertEquals(1000000, $r['target_sisa_pokok']);

        $service->jalankan([$loan->id], $this->pengurus()->id);

        $this->assertSame(['pokok' => 1000000.0, 'jasa' => 100000.0], $this->sisaJadwal($loan));
        $this->assertSame('lunas', LoanSchedule::query()->where('loan_id', $loan->id)->where('installment_number', 2)->value('status'));
        $this->assertSame('belum_bayar', LoanSchedule::query()->where('loan_id', $loan->id)->where('installment_number', 3)->value('status'));
    }

    public function test_pinjaman_yang_sudah_selaras_tidak_muncul(): void
    {
        $loan = Loan::factory()->create(['status' => 'dicairkan', 'principal_amount' => 3000000]);
        $this->jadwal($loan, 3, 1000000, 100000, fn (int $i) => $i === 1 ? [1000000, 100000] : [0, 0]);
        $this->angsuran($loan, 1000000, 100000);

        $this->assertCount(0, app(LoanScheduleAlignmentService::class)->temukan());
    }

    public function test_batalkan_mengembalikan_jadwal_dan_status_persis(): void
    {
        $loan = Loan::factory()->create(['status' => 'lunas', 'principal_amount' => 20000000]);
        $this->saldoAwal($loan, 13350000, 1335000);
        $this->jadwal($loan, 100, 133500, 13350, fn () => [133500, 13350]);
        $this->angsuran($loan, 11325000, 1335000);

        $potret = fn () => LoanSchedule::query()->where('loan_id', $loan->id)->orderBy('installment_number')
            ->get(['installment_number', 'paid_principal_amount', 'paid_interest_amount', 'paid_amount', 'status'])
            ->map(fn (LoanSchedule $s) => $s->toArray())->all();
        $sebelum = $potret();

        $service = app(LoanScheduleAlignmentService::class);
        $alignment = $service->jalankan([$loan->id], $this->pengurus()->id);
        $this->assertNotEquals($sebelum, $potret());
        $this->assertSame('dicairkan', $loan->fresh()->status);

        $service->batalkan($alignment, $this->pengurus()->id);

        $this->assertEquals($sebelum, $potret());
        $this->assertSame('lunas', $loan->fresh()->status);
        $this->assertNotNull($alignment->fresh()->reverted_at);

        $this->expectException(RuntimeException::class);
        $service->batalkan($alignment->fresh(), $this->pengurus()->id);
    }

    public function test_layar_menampilkan_pratinjau_dan_menjalankan_yang_dicentang(): void
    {
        $loan = $this->sucipto();
        $user = $this->pengurus();

        $response = $this->actingAs($user)->get(route('admin.pinjaman.penyelarasan-jadwal.index'));
        $response->assertOk();
        $response->assertSee($loan->loan_number);
        $response->assertSee('295.000');

        $this->actingAs($user)->post(route('admin.pinjaman.penyelarasan-jadwal.store'), [
            'loan_ids' => [$loan->id],
            'konfirmasi' => '1',
        ])->assertRedirect();

        $this->assertDatabaseCount('loan_schedule_alignments', 1);
        $this->assertSame(['pokok' => 295000.0, 'jasa' => 7500.0], $this->sisaJadwal($loan));

        $alignment = LoanScheduleAlignment::query()->first();
        // Sesudah dijalankan, pengurus diarahkan ke rincian penyelarasan itu.
        $this->actingAs($user)->post(route('admin.pinjaman.penyelarasan-jadwal.store'), [
            'loan_ids' => [$loan->id], 'konfirmasi' => '1',
        ])->assertRedirect(route('admin.pinjaman.penyelarasan-jadwal.index')); // sudah selaras → error, kembali ke daftar
        $this->actingAs($user)->post(route('admin.pinjaman.penyelarasan-jadwal.undo', $alignment))
            ->assertRedirect(route('admin.pinjaman.penyelarasan-jadwal.index'));
        $this->assertSame(['pokok' => 50000.0, 'jasa' => 0.0], $this->sisaJadwal($loan));
    }

    /**
     * Rincian per baris: SUCIPTO punya 200 baris; sesudah diselaraskan
     * terbayar disebar dari angsuran tertua sehingga angsuran ke-200 yang
     * tadinya "sebagian" (25.000 + 7.500) menjadi belum bayar, sedangkan
     * angsuran ke-1 tidak berubah.
     */
    public function test_rencana_memuat_rincian_sebelum_sesudah_tiap_baris(): void
    {
        $loan = $this->sucipto();

        $r = app(LoanScheduleAlignmentService::class)->rencanaUntuk($loan);

        $this->assertCount(200, $r['rincian']);
        $this->assertSame($r['baris_berubah'], collect($r['rincian'])->where('berubah', true)->count());

        $pertama = $r['rincian'][0];
        $this->assertSame(1, $pertama['no']);
        $this->assertFalse($pertama['berubah']);
        $this->assertEquals($pertama['lama'], $pertama['baru']);

        $terakhir = $r['rincian'][199];
        $this->assertSame(200, $terakhir['no']);
        $this->assertTrue($terakhir['berubah']);
        $this->assertEquals(['paid_principal_amount' => 25000.0, 'paid_interest_amount' => 7500.0, 'paid_amount' => 32500.0, 'status' => 'sebagian'], $terakhir['lama']);
        $this->assertEquals(['paid_principal_amount' => 0.0, 'paid_interest_amount' => 0.0, 'paid_amount' => 0.0, 'status' => 'belum_bayar'], $terakhir['baru']);

        // Sisa sesudah (menurut rincian) = sisa buku besar.
        $sisaPokokSesudah = collect($r['rincian'])->sum(fn (array $b) => $b['pokok'] - $b['baru']['paid_principal_amount']);
        $sisaJasaSesudah = collect($r['rincian'])->sum(fn (array $b) => $b['jasa'] - $b['baru']['paid_interest_amount']);
        $this->assertEquals(295000, $sisaPokokSesudah);
        $this->assertEquals(7500, $sisaJasaSesudah);
    }

    public function test_layar_rincian_pinjaman_menampilkan_sebelum_dan_sesudah(): void
    {
        $loan = $this->sucipto();

        $response = $this->actingAs($this->pengurus())->get(route('admin.pinjaman.penyelarasan-jadwal.show', $loan));

        $response->assertOk();
        $response->assertSee($loan->loan_number);
        $response->assertSee('Sisa pokok menurut jadwal');
        $response->assertSee('50.000');   // sebelum
        $response->assertSee('295.000');  // sesudah
        $response->assertSee('Selaraskan Pinjaman Ini');
    }

    public function test_riwayat_menyimpan_dan_menampilkan_nilai_sebelum_dan_sesudah(): void
    {
        $loan = $this->sucipto();
        $user = $this->pengurus();
        $service = app(LoanScheduleAlignmentService::class);

        $alignment = $service->jalankan([$loan->id], $user->id);

        $p = $alignment->payload[$loan->id];
        $this->assertEquals(50000, $p['sisa_pokok_jadwal_lama']);
        $this->assertEquals(295000, $p['sisa_pokok_jadwal_baru']);
        $this->assertEquals(0, $p['sisa_jasa_jadwal_lama']);
        $this->assertEquals(7500, $p['sisa_jasa_jadwal_baru']);
        $this->assertSame(array_keys($p['baris']), array_keys($p['baris_baru']));

        $rincian = $service->rincianRiwayat($alignment);
        $this->assertCount(1, $rincian);
        $this->assertSame($loan->loan_number, $rincian[0]['loan_number']);
        $this->assertSame($alignment->rows_changed, $rincian[0]['baris_berubah']);
        $baris200 = collect($rincian[0]['rincian'])->firstWhere('no', 200);
        $this->assertEquals(25000, $baris200['lama']['paid_principal_amount']);
        $this->assertEquals(0, $baris200['baru']['paid_principal_amount']);
        $this->assertSame('sebagian', $baris200['lama']['status']);
        $this->assertSame('belum_bayar', $baris200['baru']['status']);

        $response = $this->actingAs($user)->get(route('admin.pinjaman.penyelarasan-jadwal.riwayat', $alignment));
        $response->assertOk();
        $response->assertSee($loan->loan_number);
        $response->assertSee('Berlaku');
        $response->assertSee('295.000');

        // Setelah dibatalkan, rincian tetap bisa dibaca dari payload (tidak menghitung ulang).
        $service->batalkan($alignment, $user->id);
        $rincian = $service->rincianRiwayat($alignment->fresh());
        $this->assertEquals(0, collect($rincian[0]['rincian'])->firstWhere('no', 200)['baru']['paid_principal_amount']);
        $this->actingAs($user)->get(route('admin.pinjaman.penyelarasan-jadwal.riwayat', $alignment))
            ->assertOk()->assertSee('DIBATALKAN');
    }

    /** Payload dari versi sebelum kolom baris_baru ada: nilai sesudah diambil dari DB selama belum dibatalkan. */
    public function test_riwayat_lama_tanpa_baris_baru_tetap_menampilkan_nilai_sesudah(): void
    {
        $loan = $this->sucipto();
        $service = app(LoanScheduleAlignmentService::class);
        $alignment = $service->jalankan([$loan->id], $this->pengurus()->id);

        $payload = $alignment->payload;
        unset($payload[$loan->id]['baris_baru'], $payload[$loan->id]['sisa_pokok_jadwal_lama']);
        $alignment->update(['payload' => $payload]);

        $rincian = $service->rincianRiwayat($alignment->fresh());
        $baris200 = collect($rincian[0]['rincian'])->firstWhere('no', 200);
        $this->assertSame('belum_bayar', $baris200['baru']['status']);
        $this->assertNull($rincian[0]['sisa_pokok_jadwal_lama']);
    }

    public function test_tanpa_persetujuan_tidak_dijalankan_dan_peran_lain_ditolak(): void
    {
        $loan = $this->sucipto();

        $this->actingAs($this->pengurus())->post(route('admin.pinjaman.penyelarasan-jadwal.store'), [
            'loan_ids' => [$loan->id],
        ])->assertSessionHasErrors('konfirmasi');
        $this->assertDatabaseCount('loan_schedule_alignments', 0);

        $this->actingAs($this->pengurus('petugas_kredit'))
            ->get(route('admin.pinjaman.penyelarasan-jadwal.index'))
            ->assertForbidden();
        $this->actingAs($this->pengurus('petugas_kredit'))
            ->get(route('admin.pinjaman.penyelarasan-jadwal.show', $loan))
            ->assertForbidden();
    }
}
