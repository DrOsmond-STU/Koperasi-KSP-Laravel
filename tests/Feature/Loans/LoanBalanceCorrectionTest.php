<?php

namespace Tests\Feature\Loans;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\Member;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceLoan;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Loans\LoanBalanceCorrectionService;
use App\Services\Loans\LoanRepaymentService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Menu Perbaikan Saldo Pinjaman Anggota (permintaan pemilik 11 Nov 2026).
 * Kasus dari produksi: angsuran Rp 100.000 diinput 2 Okt tetapi bertanggal
 * 1 Jul (sebelum saldo awal), seluruhnya pokok — saldo per 31 Agt menjadi
 * pokok −30.000 / jasa 20.000, padahal seharusnya pokok 70.000 / jasa 20.000.
 */
class LoanBalanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $petugas;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-11 10:00:00');
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->petugas = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pengguna(string $peran): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole($peran);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    /**
     * Pinjaman migrasi: saldo awal per 31 Jul 2026 pokok 1.200.000 + jasa
     * 60.000 (3 angsuran 400.000 + 20.000). Jadwal sengaja 4 baris (jadwal
     * migrasi yang lebih besar dari saldo awal, seperti di produksi).
     */
    private function pinjamanMigrasi(): Loan
    {
        $branch = Branch::factory()->create();
        $member = Member::factory()->create();
        $product = LoanProduct::factory()->create();
        $batch = OpeningBalanceBatch::query()->create(['branch_id' => $branch->id, 'cutoff_date' => '2026-07-31', 'status' => 'locked']);
        OpeningBalanceLoan::query()->create([
            'opening_balance_batch_id' => $batch->id, 'member_id' => $member->id, 'loan_product_id' => $product->id,
            'external_loan_number' => '117-0151-01053', 'disbursement_date' => '2026-05-21', 'original_principal' => 40000000,
            'outstanding_principal' => 1200000, 'outstanding_interest' => 60000, 'tenor_days' => 100, 'remaining_tenor_days' => 3,
            'next_installment_number' => 1, 'next_due_date' => '2026-08-01', 'collectibility' => 'lancar',
        ]);
        $loan = Loan::factory()->create([
            'branch_id' => $branch->id, 'member_id' => $member->id, 'loan_product_id' => $product->id,
            'loan_number' => '117-0151-01053', 'principal_amount' => 40000000, 'status' => 'dicairkan', 'disbursed_at' => '2026-05-21',
        ]);
        for ($i = 1; $i <= 4; $i++) {
            LoanSchedule::query()->create([
                'loan_id' => $loan->id, 'installment_number' => $i, 'due_date' => Carbon::parse('2026-07-31')->addDays($i),
                'principal_amount' => 400000, 'interest_amount' => 20000, 'total_amount' => 420000, 'paid_amount' => 0, 'status' => 'belum_bayar',
            ]);
        }

        return $loan->fresh();
    }

    private function bayar(Loan $loan, string $tanggal, float $pokok, float $jasa): LoanRepayment
    {
        return app(LoanRepaymentService::class)->recordManualPayment(
            $loan->fresh(), $pokok, $jasa, 0, $this->petugas->id, null, null, Carbon::parse($tanggal),
        );
    }

    /** @return array{0: Loan, 1: LoanRepayment} pinjaman + angsuran salah tanggal */
    private function kasusMujahidin(): array
    {
        $loan = $this->pinjamanMigrasi();
        $this->bayar($loan, '2026-08-01', 330000, 0);
        $this->bayar($loan, '2026-08-02', 400000, 20000);
        $this->bayar($loan, '2026-08-03', 400000, 20000);
        Carbon::setTestNow('2026-10-02 09:44:05');
        $salah = $this->bayar($loan, '2026-07-01', 100000, 0);
        Carbon::setTestNow('2026-11-11 10:00:00');

        return [$loan->fresh(), $salah];
    }

    public function test_kartu_menghitung_saldo_per_tanggal_menurut_tanggal_transaksi_dan_menandai_tanggal_salah(): void
    {
        [$loan, $salah] = $this->kasusMujahidin();

        $k = app(LoanBalanceCorrectionService::class)->kartu($loan, Carbon::parse('2026-08-31'));

        $this->assertEqualsWithDelta(-30000, $k['saldo_per']['pokok'], 0.001);
        $this->assertEqualsWithDelta(20000, $k['saldo_per']['jasa'], 0.001);
        $this->assertEqualsWithDelta(-30000, $k['sisa_pokok'], 0.001);
        $baris = collect($k['baris'])->firstWhere(fn ($b) => $b['repayment']->id === $salah->id);
        $this->assertSame('2026-07-01', $baris['tanggal']);
        $this->assertNotEmpty($baris['catatan']);
        $this->assertContains('Sisa pokok minus (pokok terbayar lebih Rp 30.000).', $k['masalah']);
    }

    public function test_koreksi_memindahkan_tanggal_dan_memecah_pokok_jasa_dengan_jurnal_yang_benar(): void
    {
        [$loan, $salah] = $this->kasusMujahidin();
        $admin = $this->pengguna('bendahara');
        $jurnalAsal = JournalEntry::query()->with('lines')->findOrFail($salah->journal_entry_id);
        $akunKas = $jurnalAsal->lines->firstWhere(fn ($l) => (float) $l->debit > 0)->chart_of_account_id;
        $this->actingAs($admin);

        $baru = app(LoanBalanceCorrectionService::class)->koreksi($salah, [
            'tanggal' => '2026-10-02', 'pokok' => 70000, 'jasa' => 20000, 'denda' => 10000, 'alasan' => 'salah tanggal, uang diterima 2 Okt',
        ], $admin->id);

        $salah->refresh();
        $this->assertTrue($salah->isCancelled());
        $pembalik = JournalEntry::query()->findOrFail($salah->reversal_journal_entry_id);
        $this->assertSame($jurnalAsal->id, (int) $pembalik->reversal_of_entry_id);
        $this->assertSame('2026-07-01', $pembalik->entry_date->toDateString());

        $this->assertSame('2026-10-02', $baru->transaction_date->toDateString());
        $this->assertEqualsWithDelta(100000, (float) $baru->amount, 0.001);
        $jurnalBaru = JournalEntry::query()->with('lines')->findOrFail($baru->journal_entry_id);
        $this->assertSame('2026-10-02', $jurnalBaru->entry_date->toDateString());
        $product = $loan->loanProduct;
        $this->assertEqualsWithDelta(100000, (float) $jurnalBaru->lines->firstWhere('chart_of_account_id', $akunKas)->debit, 0.001);
        $this->assertEqualsWithDelta(70000, (float) $jurnalBaru->lines->firstWhere('chart_of_account_id', $product->coa_receivable_account_id)->credit, 0.001);
        $this->assertEqualsWithDelta(20000, (float) $jurnalBaru->lines->firstWhere('chart_of_account_id', $product->coa_interest_income_account_id)->credit, 0.001);
        $this->assertEqualsWithDelta(10000, (float) $jurnalBaru->lines->firstWhere('chart_of_account_id', $product->coa_penalty_receivable_account_id)->credit, 0.001);

        $k = app(LoanBalanceCorrectionService::class)->kartu($loan->fresh(), Carbon::parse('2026-08-31'));
        $this->assertEqualsWithDelta(70000, $k['saldo_per']['pokok'], 0.001);
        $this->assertEqualsWithDelta(20000, $k['saldo_per']['jasa'], 0.001);
        $this->assertEqualsWithDelta(0, $k['sisa_pokok'], 0.001);
        $this->assertEqualsWithDelta(0, $k['sisa_jasa'], 0.001);

        // jadwal & status diselaraskan: buku besar lunas → pinjaman lunas
        $this->assertSame('lunas', $loan->fresh()->status);
        $this->assertSame(0, LoanSchedule::query()->where('loan_id', $loan->id)->where('status', '!=', 'lunas')->count());
        $this->assertTrue(AuditLog::query()->where('action', 'koreksi_angsuran')->where('auditable_id', $baru->id)->exists());

        $t = DB::selectOne('SELECT ROUND(SUM(debit),2) d, ROUND(SUM(credit),2) k FROM journal_lines');
        $this->assertSame((string) $t->d, (string) $t->k);
    }

    public function test_koreksi_nol_membatalkan_angsuran_tanpa_pengganti(): void
    {
        [$loan, $salah] = $this->kasusMujahidin();
        $admin = $this->pengguna('bendahara');
        $jumlahAngsuran = LoanRepayment::query()->count();
        $isian = ['tanggal' => '2026-07-01', 'pokok' => 0, 'jasa' => 0, 'denda' => 0, 'alasan' => 'transaksi seharusnya tidak ada', 'per' => '2026-08-31'];

        // Pratinjau: tidak ditolak, tidak menyimpan apa pun.
        $this->actingAs($admin)->get(route('admin.pinjaman.perbaikan-saldo.koreksi', ['repayment' => $salah] + $isian + ['pratinjau' => 1]))
            ->assertOk()->assertSee('Pratinjau sesudah koreksi')->assertSee('tanpa pengganti')->assertDontSee('tidak bisa diterapkan');
        $this->assertFalse($salah->fresh()->isCancelled());

        $this->actingAs($admin)->post(route('admin.pinjaman.perbaikan-saldo.simpan', $salah), $isian + ['konfirmasi' => 1])
            ->assertRedirect(route('admin.pinjaman.perbaikan-saldo.show', ['loan' => $loan->id, 'per' => '2026-08-31']))
            ->assertSessionHas('status');

        $salah->refresh();
        $this->assertTrue($salah->isCancelled());
        $this->assertSame($jumlahAngsuran, LoanRepayment::query()->count(), 'Tidak boleh ada angsuran pengganti.');
        $pembalik = JournalEntry::query()->findOrFail($salah->reversal_journal_entry_id);
        $this->assertSame((int) $salah->journal_entry_id, (int) $pembalik->reversal_of_entry_id);
        $this->assertTrue(AuditLog::query()->where('action', 'koreksi_angsuran')->where('auditable_id', $salah->id)->exists());

        $k = app(LoanBalanceCorrectionService::class)->kartu($loan->fresh(), Carbon::parse('2026-08-31'));
        $this->assertEqualsWithDelta(70000, $k['saldo_per']['pokok'], 0.001);
        $this->assertEqualsWithDelta(20000, $k['saldo_per']['jasa'], 0.001);

        $t = DB::selectOne('SELECT ROUND(SUM(debit),2) d, ROUND(SUM(credit),2) k FROM journal_lines');
        $this->assertSame((string) $t->d, (string) $t->k);
    }

    public function test_koreksi_ditolak_bila_tanggal_tidak_sesudah_saldo_awal_atau_tidak_ada_perubahan(): void
    {
        [$loan, $salah] = $this->kasusMujahidin();
        $benar = LoanRepayment::query()->where('loan_id', $loan->id)->whereDate('transaction_date', '2026-08-02')->firstOrFail();
        $service = app(LoanBalanceCorrectionService::class);

        foreach ([
            [$salah, '2026-07-31', 100000, 0, 'sesudah tanggal saldo awal'],
            [$benar, '2026-08-02', 400000, 20000, 'Tidak ada yang berubah'],
        ] as [$angsuran, $tgl, $pokok, $jasa, $pesan]) {
            try {
                $service->koreksi($angsuran, ['tanggal' => $tgl, 'pokok' => $pokok, 'jasa' => $jasa, 'denda' => 0, 'alasan' => 'uji'], $this->petugas->id);
                $this->fail('Koreksi seharusnya ditolak');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($pesan, $e->getMessage());
            }
            $this->assertFalse($angsuran->fresh()->isCancelled());
        }
    }

    public function test_alur_layar_pratinjau_tidak_menyimpan_lalu_simpan_menerapkan(): void
    {
        [$loan, $salah] = $this->kasusMujahidin();
        $admin = $this->pengguna('bendahara');
        $jumlahAngsuran = LoanRepayment::query()->count();
        $jumlahJurnal = JournalEntry::query()->count();

        $this->actingAs($admin)->get(route('admin.pinjaman.perbaikan-saldo.index'))
            ->assertOk()->assertSee('117-0151-01053')->assertSee('pokok minus');

        $this->actingAs($admin)->get(route('admin.pinjaman.perbaikan-saldo.show', ['loan' => $loan, 'per' => '2026-08-31']))
            ->assertOk()->assertSee('Kartu Pinjaman 117-0151-01053')->assertSee('-30.000');

        $isian = ['tanggal' => '2026-10-02', 'pokok' => 70000, 'jasa' => 20000, 'denda' => 10000, 'alasan' => 'salah tanggal', 'per' => '2026-08-31'];
        $this->actingAs($admin)->get(route('admin.pinjaman.perbaikan-saldo.koreksi', ['repayment' => $salah] + $isian + ['pratinjau' => 1]))
            ->assertOk()->assertSee('Pratinjau sesudah koreksi')->assertSee('Simpan Koreksi');
        $this->assertSame($jumlahAngsuran, LoanRepayment::query()->count());
        $this->assertSame($jumlahJurnal, JournalEntry::query()->count());
        $this->assertFalse($salah->fresh()->isCancelled());

        $this->actingAs($admin)->post(route('admin.pinjaman.perbaikan-saldo.simpan', $salah), $isian)
            ->assertSessionHasErrors('konfirmasi');
        $this->assertFalse($salah->fresh()->isCancelled());

        $this->actingAs($admin)->post(route('admin.pinjaman.perbaikan-saldo.simpan', $salah), $isian + ['konfirmasi' => 1])
            ->assertRedirect(route('admin.pinjaman.perbaikan-saldo.show', ['loan' => $loan->id, 'per' => '2026-08-31']));
        $this->assertTrue($salah->fresh()->isCancelled());
        $this->assertSame($jumlahAngsuran + 1, LoanRepayment::query()->count());
    }

    public function test_selaraskan_membuka_kembali_pinjaman_lunas_yang_masih_bersisa(): void
    {
        $loan = $this->pinjamanMigrasi();
        $this->bayar($loan, '2026-08-01', 400000, 20000);
        // jadwal & status rusak: semua lunas padahal buku besar masih bersisa
        LoanSchedule::query()->where('loan_id', $loan->id)->update(['paid_principal_amount' => 400000, 'paid_interest_amount' => 20000, 'paid_amount' => 420000, 'status' => 'lunas']);
        $loan->update(['status' => 'lunas']);
        $admin = $this->pengguna('bendahara');

        $this->actingAs($admin)->from(route('admin.pinjaman.perbaikan-saldo.show', $loan))
            ->post(route('admin.pinjaman.perbaikan-saldo.selaraskan', $loan))
            ->assertRedirect(route('admin.pinjaman.perbaikan-saldo.show', $loan));

        $this->assertSame('dicairkan', $loan->fresh()->status);
        $k = app(LoanBalanceCorrectionService::class)->kartu($loan->fresh());
        $this->assertEqualsWithDelta(800000, $k['sisa_pokok'], 0.001);
        $this->assertEqualsWithDelta(800000, $k['jadwal']['sisa_pokok_jadwal'], 0.001);
    }

    public function test_peran_tanpa_hak_saldo_awal_update_ditolak(): void
    {
        [$loan, $salah] = $this->kasusMujahidin();
        $petugas = $this->pengguna('petugas_kredit');

        $this->actingAs($petugas)->get(route('admin.pinjaman.perbaikan-saldo.index'))->assertForbidden();
        $this->actingAs($petugas)->get(route('admin.pinjaman.perbaikan-saldo.show', $loan))->assertForbidden();
        $this->actingAs($petugas)->post(route('admin.pinjaman.perbaikan-saldo.simpan', $salah), [
            'tanggal' => '2026-10-02', 'pokok' => 100000, 'jasa' => 0, 'denda' => 0, 'alasan' => 'x', 'konfirmasi' => 1,
        ])->assertForbidden();
        $this->assertFalse($salah->fresh()->isCancelled());
    }
}
