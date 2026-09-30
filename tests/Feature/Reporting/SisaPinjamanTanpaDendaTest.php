<?php

namespace Tests\Feature\Reporting;

use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceLoan;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Dashboard\MainDashboardService;
use App\Services\Loans\SisaPinjamanCalculator;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Laporan 28 Sep 2026 (sik-kppd.com/admin/laporan/pembayaran_angsuran):
 *
 *   MIGRASI-1433  WATI  KPPD Pusat  Rp 34.546.100  Rp 31.150.000  Rp 3.296.100  Rp 100.000
 *
 * Seluruh pokok sudah dibayar, tapi Sisa Pinjaman tampil Rp 100.000 — persis
 * sebesar dendanya. "Nilai denda tidak boleh ikut dalam perhitungan sisa
 * pinjaman." Baris-baris di sini ditulis persis seperti yang tersimpan di
 * database produksi (balance_after yang keliru ikut disimpan): laporan,
 * cetakan, form Catat Angsuran, dan dashboard harus tetap benar tanpa
 * mengubahnya.
 */
class SisaPinjamanTanpaDendaTest extends TestCase
{
    use RefreshDatabase;

    private function pengurus(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $lain
     */
    private function angsuran(Loan $loan, string $tanggal, float $pokok, float $jasa, float $denda, float $balanceAfter, array $lain = []): LoanRepayment
    {
        return LoanRepayment::query()->create([
            'branch_id' => $loan->branch_id,
            'loan_id' => $loan->id,
            'amount' => $pokok + $jasa + $denda,
            'principal_portion' => $pokok,
            'interest_portion' => $jasa,
            'penalty_portion' => $denda,
            'balance_after' => $balanceAfter,
            'paid_at' => $tanggal,
            'transaction_date' => $tanggal,
            'created_by' => User::factory()->create()->id,
            ...$lain,
        ]);
    }

    /** Pinjaman hasil migrasi saldo awal, dinomori seperti OpeningBalanceLockService. */
    private function pinjamanMigrasi(float $plafon, float $sisaPokokCutoff): Loan
    {
        $loan = Loan::factory()->create(['status' => 'dicairkan', 'principal_amount' => $plafon]);

        $batch = OpeningBalanceBatch::query()->create([
            'branch_id' => Branch::factory()->create()->id,
            'cutoff_date' => '2026-08-01',
            'status' => 'locked',
        ]);

        $baris = OpeningBalanceLoan::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'member_id' => $loan->member_id,
            'loan_product_id' => $loan->loan_product_id,
            'external_loan_number' => null,
            'disbursement_date' => '2025-08-01',
            'original_principal' => $plafon,
            'outstanding_principal' => $sisaPokokCutoff,
            'outstanding_interest' => 0,
            'tenor_days' => 24,
            'remaining_tenor_days' => 12,
            'next_installment_number' => 13,
            'next_due_date' => '2026-09-01',
            'collectibility' => 'lancar',
        ]);

        $loan->update(['loan_number' => 'MIGRASI-'.$baris->id]);

        return $loan->fresh();
    }

    /**
     * @return Collection<string, string> loan_number|tanggal => Sisa Pinjaman
     */
    private function sisaDiLaporan(): Collection
    {
        $response = $this->actingAs($this->pengurus())
            ->get(route('admin.laporan.show', 'pembayaran_angsuran'));

        $response->assertOk();

        return collect($response->viewData('rows'))
            ->mapWithKeys(fn (array $row) => [$row['loan_number'].'|'.$row['tanggal'] => $row['saldo_akhir']]);
    }

    public function test_kasus_wati_pelunasan_dengan_denda_bersisa_nol(): void
    {
        $loan = $this->pinjamanMigrasi(plafon: 50000000, sisaPokokCutoff: 31150000);

        // Persis baris produksi: jadwal masih menyisakan Rp 100.000, jadi
        // balance_after tersimpan 100.000 walau pokoknya sudah lunas.
        $this->angsuran($loan, '2026-09-20', 31150000, 3296100, 100000, balanceAfter: 100000);

        $sisa = $this->sisaDiLaporan();

        $this->assertSame('Rp 0', $sisa[$loan->loan_number.'|20-09-2026']);
    }

    public function test_denda_dan_jasa_tidak_mengurangi_maupun_menambah_sisa_pinjaman(): void
    {
        $loan = Loan::factory()->create(['status' => 'dicairkan', 'principal_amount' => 3000000]);

        $this->angsuran($loan, '2026-09-01', 1000000, 100000, 50000, balanceAfter: 2150000);
        $this->angsuran($loan, '2026-09-10', 1000000, 100000, 0, balanceAfter: 1100000, lain: [
            'cancelled_at' => now(),
            'cancellation_reason' => 'salah catat',
        ]);
        $this->angsuran($loan, '2026-09-15', 1000000, 100000, 75000, balanceAfter: 1025000);

        $sisa = $this->sisaDiLaporan();

        $this->assertSame('Rp 2.000.000', $sisa[$loan->loan_number.'|01-09-2026']);
        // Dibatalkan: tidak mengubah sisa pinjaman, jadi barisnya tidak
        // menampilkan sisa sama sekali, dan baris sesudahnya tidak ikut turun.
        $this->assertSame('-', $sisa[$loan->loan_number.'|10-09-2026']);
        $this->assertSame('Rp 1.000.000', $sisa[$loan->loan_number.'|15-09-2026']);

        $this->assertSame([$loan->id => 1000000.0], app(SisaPinjamanCalculator::class)->saatIni([$loan->id]));
    }

    /**
     * Riwayat dari sistem lama adalah arsip: sisanya ditampilkan apa adanya
     * dan tidak mengurangi posisi cutoff (yang sudah memperhitungkannya).
     */
    public function test_riwayat_migrasi_tampil_apa_adanya_dan_tidak_mengurangi_posisi_cutoff(): void
    {
        $loan = $this->pinjamanMigrasi(plafon: 24000000, sisaPokokCutoff: 12000000);

        $this->angsuran($loan, '2026-07-01', 1000000, 200000, 0, balanceAfter: 12000000, lain: ['migrated_at' => now()]);
        $this->angsuran($loan, '2026-09-01', 1000000, 200000, 25000, balanceAfter: 999999);

        $sisa = $this->sisaDiLaporan();

        $this->assertSame('Rp 12.000.000', $sisa[$loan->loan_number.'|01-07-2026']);
        $this->assertSame('Rp 11.000.000', $sisa[$loan->loan_number.'|01-09-2026']);
    }

    /**
     * Saldo berjalan dihitung dari SEMUA angsuran pinjaman itu, termasuk yang
     * tercatat di cabang yang tidak boleh dilihat pengguna — kalau tidak,
     * sisa pada baris yang terlihat melompat-lompat tergantung siapa yang
     * membuka laporan.
     */
    public function test_saldo_berjalan_utuh_walau_sebagian_angsuran_di_luar_cabang_pengguna(): void
    {
        $loan = Loan::factory()->create(['status' => 'dicairkan', 'principal_amount' => 3000000]);
        $cabangLain = Branch::factory()->create();

        $this->angsuran($loan, '2026-09-01', 1000000, 0, 0, balanceAfter: 0, lain: ['branch_id' => $cabangLain->id]);
        $this->angsuran($loan, '2026-09-15', 500000, 0, 10000, balanceAfter: 0);

        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create([
            'user_id' => $user->id,
            'scope_type' => 'single',
            'single_branch_id' => $loan->branch_id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.laporan.show', 'pembayaran_angsuran'));
        $response->assertOk();

        $rows = collect($response->viewData('rows'));
        $this->assertCount(1, $rows);
        $this->assertSame('Rp 1.500.000', $rows->first()['saldo_akhir']);
    }

    /**
     * Cetakan yang juga menampilkan sisa pinjaman: bukti angsuran, jadwal &
     * historis angsuran, dan daftar pinjaman anggota. Isi PDF tidak bisa
     * dibaca di sini, jadi HTML sumbernya dirender ulang dari data yang
     * diterima view.
     */
    public function test_cetakan_pinjaman_memakai_sisa_tanpa_denda(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $user = $this->pengurus();
        Role::findOrCreate('print-tester')->givePermissionTo(['pinjaman.print', 'pinjaman.read']);
        $user->assignRole('print-tester');

        $loan = $this->pinjamanMigrasi(plafon: 50000000, sisaPokokCutoff: 31150000);
        $repayment = $this->angsuran($loan, '2026-09-20', 31150000, 3296100, 100000, balanceAfter: 100000);

        $html = [];
        View::composer('prints.loans.*', function ($view) use (&$html) {
            $html[$view->getName()] = $view->getData();
        });

        $this->actingAs($user)->get(route('admin.print.loan-repayment.show', $repayment))->assertOk();
        $this->actingAs($user)->get(route('admin.print.loans.schedule', $loan))->assertOk();
        $this->actingAs($user)->get(route('admin.print.loans.index', ['member_id' => $loan->member_id]))->assertOk();

        $this->assertSame(0.0, $html['prints.loans.repayment-receipt']['sisaPinjaman']);
        $this->assertSame([$repayment->id => 0.0], $html['prints.loans.schedule']['sisaSetelah']);
        $this->assertSame([$loan->id => 0.0], $html['prints.loans.list']['sisaPokok']);

        $kwitansi = view('prints.loans.repayment-receipt', $html['prints.loans.repayment-receipt'])->render();
        $this->assertStringContainsString('Sisa Pinjaman</td><td style="padding:3px 0;">: Rp 0<', $kwitansi);
        $this->assertStringNotContainsString('Sisa Tunggakan', $kwitansi);
    }

    /**
     * "Saldo Outstanding" di form Catat Angsuran harus sama dengan Sisa
     * Pinjaman di laporan — dulu dihitung dari jadwal, sehingga WATI tetap
     * tampil Rp 100.000.
     */
    public function test_saldo_outstanding_form_catat_angsuran_tanpa_denda(): void
    {
        $loan = $this->pinjamanMigrasi(plafon: 50000000, sisaPokokCutoff: 31150000);
        LoanSchedule::query()->create([
            'loan_id' => $loan->id,
            'installment_number' => 1,
            'due_date' => '2026-09-01',
            'principal_amount' => 31150000,
            'interest_amount' => 3396100,
            'total_amount' => 34546100,
            'paid_principal_amount' => 31050000,
            'paid_interest_amount' => 3396100,
            'paid_amount' => 34446100,
            'status' => 'sebagian',
        ]);
        $this->angsuran($loan, '2026-09-20', 31150000, 3296100, 100000, balanceAfter: 100000);

        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_kredit');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $response = $this->actingAs($user)->get(route('staf.angsuran.create'));
        $response->assertOk();

        $this->assertSame(0.0, $response->viewData('outstandingBalances')[$loan->id]);
    }

    /** Dashboard: Pinjaman Outstanding = sisa pokok, bukan plafon. */
    public function test_dashboard_pinjaman_outstanding_memakai_sisa_pokok(): void
    {
        $loan = Loan::factory()->create(['status' => 'dicairkan', 'principal_amount' => 3000000, 'collectibility' => 'lancar']);
        $this->angsuran($loan, '2026-09-01', 1000000, 100000, 50000, balanceAfter: 2150000);

        $summary = app(MainDashboardService::class)->summary();

        $this->assertEquals(2000000, $summary['total_loan_outstanding']);
        $this->assertEquals(2000000, $summary['loan_outstanding_by_collectibility']->firstWhere('collectibility', 'lancar')->total);
    }

    private function barisSaldoAwal(Loan $loan, ?string $nomorLama, float $pokok, float $jasa): OpeningBalanceLoan
    {
        return OpeningBalanceLoan::query()->create([
            'opening_balance_batch_id' => OpeningBalanceBatch::query()->create([
                'branch_id' => $loan->branch_id,
                'cutoff_date' => '2026-07-31',
                'status' => 'locked',
            ])->id,
            'member_id' => $loan->member_id,
            'loan_product_id' => $loan->loan_product_id,
            'external_loan_number' => $nomorLama,
            'disbursement_date' => '2024-05-16',
            'original_principal' => $loan->principal_amount,
            'outstanding_principal' => $pokok,
            'outstanding_interest' => $jasa,
            'tenor_days' => 24,
            'remaining_tenor_days' => 12,
            'next_installment_number' => 13,
            'next_due_date' => '2026-08-31',
            'collectibility' => 'lancar',
        ]);
    }

    /**
     * Produksi 30 Sep 2026: saldo awal diimpor ulang (baris 1291–1436) dan
     * nomor pinjaman yang sudah ada disimpan sebagai nomor lama, mis. baris
     * 1291 bernomor lama "MIGRASI-1238" (YULIAH TINDAS, sisa pokok
     * 1.558.400). Baris 1238 sudah tidak ada, jadi pinjaman MIGRASI-1238
     * terbaca bersisa plafon 3.681.600.
     */
    public function test_saldo_awal_impor_ulang_dicocokkan_lewat_nomor_lama(): void
    {
        $loan = Loan::factory()->create(['status' => 'dicairkan', 'principal_amount' => 3681600, 'loan_number' => 'MIGRASI-999238']);
        $this->barisSaldoAwal($loan, 'MIGRASI-999238', 1558400, 0);

        $posisi = app(SisaPinjamanCalculator::class)->posisiAwal(collect([$loan]));

        $this->assertSame(['pokok' => 1558400.0, 'jasa' => 0.0, 'migrasi' => true], $posisi[$loan->id]);
    }

    /**
     * Id baris hasil impor ulang bisa sama dengan angka di nomor MIGRASI-…
     * pinjaman anggota lain: baris 1318 kini milik ACHMAD BAEHAQI (nomor
     * lama "MIGRASI-1795", pokok 2.000.000), sementara pinjaman MIGRASI-1318
     * milik anggota lain punya barisnya sendiri. Masing-masing harus membaca
     * barisnya sendiri.
     */
    public function test_id_baris_yang_bentrok_tidak_dipakai_anggota_lain(): void
    {
        $achmad = Loan::factory()->create(['status' => 'dicairkan', 'principal_amount' => 80000000, 'loan_number' => 'MIGRASI-991795']);
        $barisAchmad = $this->barisSaldoAwal($achmad, 'MIGRASI-991795', 2000000, 2000000);

        $lain = Loan::factory()->create(['status' => 'dicairkan', 'principal_amount' => 50000000, 'loan_number' => 'MIGRASI-'.$barisAchmad->id]);
        $this->barisSaldoAwal($lain, 'MIGRASI-'.$barisAchmad->id, 42469600, 3100000);

        $posisi = app(SisaPinjamanCalculator::class)->posisiAwal(collect([$achmad, $lain]));

        $this->assertSame(['pokok' => 2000000.0, 'jasa' => 2000000.0, 'migrasi' => true], $posisi[$achmad->id]);
        $this->assertSame(['pokok' => 42469600.0, 'jasa' => 3100000.0, 'migrasi' => true], $posisi[$lain->id]);
    }

    /** Baris milik anggota lain tidak pernah dipakai, walau id-nya cocok. */
    public function test_migrasi_id_milik_anggota_lain_diabaikan(): void
    {
        $pemilik = Loan::factory()->create(['status' => 'lunas', 'principal_amount' => 9000000]);
        $baris = $this->barisSaldoAwal($pemilik, null, 6000000, 0);

        $asing = Loan::factory()->create(['status' => 'dicairkan', 'principal_amount' => 4000000, 'loan_number' => 'MIGRASI-'.$baris->id]);

        $posisi = app(SisaPinjamanCalculator::class)->posisiAwal(collect([$asing]));

        $this->assertSame(['pokok' => 4000000.0, 'jasa' => null, 'migrasi' => false], $posisi[$asing->id]);
    }
}
