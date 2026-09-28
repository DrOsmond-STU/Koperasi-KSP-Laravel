<?php

namespace Tests\Feature\Loans;

use App\Exceptions\Loans\LoanRepaymentException;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanSchedule;
use App\Models\User;
use App\Services\Loans\LoanRepaymentService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catat Angsuran staf (recordManualPayment) — laporan 28 Sep 2026,
 * MIGRASI-1433 a.n. WATI: "dengan adanya denda kok malah menambah saldo
 * pinjaman?".
 *
 * Aturannya: Pokok hanya mengurangi pokok jadwal, Jasa hanya mengurangi jasa
 * jadwal (jasa wajib dibayar — tidak pernah dihapuskan), Denda tidak pernah
 * menyentuh jadwal. Dulu Pokok+Jasa digabung lalu dibagi "jasa dulu", jadi
 * pembagian staf tidak tercermin di jadwal.
 */
class CatatAngsuranDendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    /** Tiga angsuran @ Rp 1.000.000 pokok + Rp 100.000 jasa. */
    private function pinjaman(): Loan
    {
        $product = LoanProduct::factory()->create();
        $loan = Loan::factory()->create(['loan_product_id' => $product->id, 'status' => 'dicairkan', 'principal_amount' => 3000000]);

        for ($i = 1; $i <= 3; $i++) {
            LoanSchedule::query()->create([
                'loan_id' => $loan->id,
                'installment_number' => $i,
                'due_date' => now()->addMonths($i),
                'principal_amount' => 1000000,
                'interest_amount' => 100000,
                'total_amount' => 1100000,
                'paid_amount' => 0,
                'status' => 'belum_bayar',
            ]);
        }

        return $loan->fresh();
    }

    private function bayar(Loan $loan, float $pokok, float $jasa, float $denda)
    {
        return app(LoanRepaymentService::class)->recordManualPayment($loan, $pokok, $jasa, $denda, User::factory()->create()->id);
    }

    private function sisaJadwal(Loan $loan): array
    {
        $jadwal = LoanSchedule::query()->where('loan_id', $loan->id)->get();

        return [
            'pokok' => round((float) $jadwal->sum('principal_amount') - (float) $jadwal->sum('paid_principal_amount'), 2),
            'jasa' => round((float) $jadwal->sum('interest_amount') - (float) $jadwal->sum('paid_interest_amount'), 2),
        ];
    }

    public function test_denda_tidak_mengubah_jadwal_maupun_sisa_pinjaman(): void
    {
        $loan = $this->pinjaman();

        $repayment = $this->bayar($loan, 1000000, 100000, 50000);

        $this->assertEquals(1150000, $repayment->amount);
        $this->assertEquals(2200000, $repayment->balance_after);
        $this->assertSame(['pokok' => 2000000.0, 'jasa' => 200000.0], $this->sisaJadwal($loan));
        $this->assertDatabaseHas('loan_schedules', ['loan_id' => $loan->id, 'installment_number' => 1, 'status' => 'lunas']);
        $this->assertDatabaseHas('loan_schedules', ['loan_id' => $loan->id, 'installment_number' => 2, 'paid_amount' => 0]);
    }

    /**
     * Kasus WATI: seluruh pokok dan seluruh jasa dibayar, ditambah denda.
     * Pinjaman harus lunas dengan sisa nol — denda tidak tertinggal sebagai
     * sisa pinjaman.
     */
    public function test_pelunasan_dengan_denda_melunasi_pinjaman_tanpa_sisa(): void
    {
        $loan = $this->pinjaman();

        $repayment = $this->bayar($loan, 3000000, 300000, 100000);

        $this->assertEquals(3400000, $repayment->amount);
        $this->assertEquals(0, $repayment->balance_after);
        $this->assertSame(['pokok' => 0.0, 'jasa' => 0.0], $this->sisaJadwal($loan));
        $this->assertSame('lunas', $loan->fresh()->status);
    }

    /** Dulu 100.000 pertama masuk jasa baris 1; sekarang pokok tetap pokok. */
    public function test_pokok_hanya_mengurangi_pokok_jadwal(): void
    {
        $loan = $this->pinjaman();

        $this->bayar($loan, 1500000, 0, 0);

        $this->assertSame(['pokok' => 1500000.0, 'jasa' => 300000.0], $this->sisaJadwal($loan));
        $this->assertSame('dicairkan', $loan->fresh()->status);
    }

    /** Jasa wajib dibayar: pokok lunas tapi jasa belum → pinjaman belum lunas. */
    public function test_jasa_yang_belum_dibayar_tetap_tagihan(): void
    {
        $loan = $this->pinjaman();

        $this->bayar($loan, 3000000, 250000, 50000);

        $this->assertSame(['pokok' => 0.0, 'jasa' => 50000.0], $this->sisaJadwal($loan));
        $this->assertSame('dicairkan', $loan->fresh()->status);
    }

    public function test_jasa_melebihi_sisa_jasa_jadwal_ditolak(): void
    {
        $loan = $this->pinjaman();

        $this->expectException(LoanRepaymentException::class);
        $this->expectExceptionMessage('melebihi sisa jasa');

        $this->bayar($loan, 0, 300001, 0);
    }

    public function test_pokok_melebihi_sisa_pokok_jadwal_ditolak(): void
    {
        $loan = $this->pinjaman();

        $this->expectException(LoanRepaymentException::class);
        $this->expectExceptionMessage('melebihi sisa pokok');

        $this->bayar($loan, 3000001, 0, 0);
    }

    public function test_jurnal_memisahkan_pokok_jasa_dan_denda(): void
    {
        $loan = $this->pinjaman();
        $product = $loan->loanProduct;

        $repayment = $this->bayar($loan, 1000000, 100000, 50000);
        $lines = $repayment->journalEntry->lines()->get();

        $this->assertEquals(1150000, $lines->sum('debit'));
        $this->assertEquals(1000000, $lines->firstWhere('chart_of_account_id', $product->coa_receivable_account_id)->credit);
        $this->assertEquals(100000, $lines->firstWhere('chart_of_account_id', $product->coa_interest_income_account_id)->credit);
        $this->assertEquals(50000, $lines->firstWhere('chart_of_account_id', $product->coa_penalty_receivable_account_id)->credit);
    }

    /**
     * Keadaan WATI di produksi per 28 Sep 2026: 200 angsuran harian @ 300.000
     * + 30.000, terbayar s/d baris 95 dan baris 96 sebagian (pokok 73.900,
     * jasa 30.000). Koreksinya: samakan baris 96 dengan buku besar (pokok
     * 100.000, jasa 3.900), lalu catat pelunasan tanpa denda.
     */
    public function test_koreksi_wati_pelunasan_tanpa_denda_melunasi_pinjaman(): void
    {
        $product = LoanProduct::factory()->create();
        $loan = Loan::factory()->create(['loan_product_id' => $product->id, 'status' => 'dicairkan', 'principal_amount' => 60000000]);

        for ($i = 1; $i <= 200; $i++) {
            [$pokok, $jasa] = match (true) {
                $i <= 95 => [300000, 30000],
                $i === 96 => [73900, 30000],
                default => [0, 0],
            };
            LoanSchedule::query()->create([
                'loan_id' => $loan->id,
                'installment_number' => $i,
                'due_date' => now()->addDays($i),
                'principal_amount' => 300000,
                'interest_amount' => 30000,
                'total_amount' => 330000,
                'paid_principal_amount' => $pokok,
                'paid_interest_amount' => $jasa,
                'paid_amount' => $pokok + $jasa,
                'status' => $i <= 95 ? 'lunas' : ($i === 96 ? 'sebagian' : 'belum_bayar'),
            ]);
        }

        // Tanpa koreksi baris 96, jasa buku besar 3.146.100 melebihi sisa
        // jasa jadwal (3.120.000) dan ditolak.
        $this->assertSame(['pokok' => 31426100.0, 'jasa' => 3120000.0], $this->sisaJadwal($loan));

        LoanSchedule::query()->where('loan_id', $loan->id)->where('installment_number', 96)->update([
            'paid_principal_amount' => 100000,
            'paid_interest_amount' => 3900,
        ]);

        $repayment = $this->bayar($loan->fresh(), 31400000, 3146100, 0);

        $this->assertEquals(34546100, $repayment->amount);
        $this->assertEquals(0, $repayment->balance_after);
        $this->assertSame(['pokok' => 0.0, 'jasa' => 0.0], $this->sisaJadwal($loan));
        $this->assertSame('lunas', $loan->fresh()->status);
    }

    public function test_pembatalan_mengembalikan_jadwal_persis(): void
    {
        $loan = $this->pinjaman();
        $repayment = $this->bayar($loan, 3000000, 300000, 100000);

        app(LoanRepaymentService::class)->reverseRepayment($repayment, 'salah catat', User::factory()->create()->id);

        $this->assertSame(['pokok' => 3000000.0, 'jasa' => 300000.0], $this->sisaJadwal($loan));
        $this->assertSame('dicairkan', $loan->fresh()->status);
    }
}
