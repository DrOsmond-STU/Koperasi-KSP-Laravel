<?php

namespace Tests\Feature\Loans;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\Loans\LoanApprovalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jurnal pencairan: tepat dua baris, keduanya sebesar pokok penuh.
 *
 * Dulu ada baris ketiga (Cr Pendapatan Provisi) dan kas hanya dikredit
 * sebesar pokok dikurangi provisi — pinjaman 30 juta cair 29,7 juta.
 * Instruksi pengurus 16 Sep 2026: biaya admin dijurnal manual, tidak
 * otomatis saat pencairan. Uji ini mengunci keduanya: baris provisinya
 * hilang, DAN kas dikredit penuh. Menghapus barisnya saja tanpa
 * membetulkan nilai kas akan membuat jurnalnya tidak seimbang.
 */
class LoanDisbursementJournalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    private int $nomorKas = 0;

    /** @return array{0: Loan, 1: ChartOfAccount, 2: LoanProduct} */
    private function pinjaman(float $plafon = 30_000_000, float $persenProvisi = 1.0): array
    {
        // Kode akun unik per pemanggilan: satu uji bisa mencairkan dua
        // pinjaman untuk membandingkan dua persentase provisi.
        $kas = ChartOfAccount::factory()->create([
            'code' => '110120'.$this->nomorKas++, 'name' => 'KAS KECIL (USP)', 'type' => 'ASET',
            'normal_balance' => 'DEBIT', 'statement' => 'NERACA', 'is_postable' => true,
        ]);
        $branch = Branch::factory()->create(['cash_account_id' => $kas->id]);
        $product = LoanProduct::factory()->create(['provision_fee_percentage' => $persenProvisi]);

        $loan = Loan::factory()->create([
            'branch_id' => $branch->id,
            'loan_product_id' => $product->id,
            'created_by' => User::factory()->create()->id,
            'required_approval_count' => 1,
            'principal_amount' => $plafon,
            'submitted_at' => '2026-08-04',
        ]);

        return [$loan, $kas, $product];
    }

    private function cairkan(Loan $loan): JournalEntry
    {
        app(LoanApprovalService::class)->approve($loan, User::factory()->create(), null, '2026-08-06');

        return JournalEntry::query()
            ->where('source_type', Loan::class)
            ->where('source_id', $loan->id)
            ->with('lines.account')
            ->firstOrFail();
    }

    public function test_the_journal_has_exactly_two_lines(): void
    {
        [$loan] = $this->pinjaman();

        $this->assertCount(2, $this->cairkan($loan)->lines);
    }

    public function test_cash_is_credited_the_full_principal_not_net_of_provision(): void
    {
        [$loan, $kas, $product] = $this->pinjaman(30_000_000, 1.0);

        $entry = $this->cairkan($loan);
        $barisKas = $entry->lines->firstWhere('chart_of_account_id', $kas->id);

        $this->assertNotNull($barisKas, 'Baris kas harus ada.');
        $this->assertEqualsWithDelta(30_000_000, (float) $barisKas->credit, 0.01, 'Kas dikredit penuh, bukan 29.700.000.');
        $this->assertEqualsWithDelta(30_000_000, (float) $entry->lines->firstWhere('chart_of_account_id', $product->coa_receivable_account_id)->debit, 0.01);
    }

    public function test_no_provision_income_line_is_posted(): void
    {
        [$loan, , $product] = $this->pinjaman(30_000_000, 1.0);

        $this->assertNull(
            $this->cairkan($loan)->lines->firstWhere('chart_of_account_id', $product->coa_provision_income_account_id),
            'Pendapatan provisi tidak boleh ikut dijurnal otomatis — dijurnal manual oleh pengurus.',
        );
    }

    public function test_the_entry_stays_balanced(): void
    {
        [$loan] = $this->pinjaman(30_000_000, 1.0);
        $entry = $this->cairkan($loan);

        $this->assertEqualsWithDelta(
            (float) $entry->lines->sum('debit'),
            (float) $entry->lines->sum('credit'),
            0.01,
        );
    }

    /** Kolom provisi pada pinjaman tidak boleh mengaku ada potongan yang tidak pernah dijurnal. */
    public function test_the_loan_records_no_provision_fee(): void
    {
        [$loan] = $this->pinjaman(30_000_000, 1.0);
        $this->cairkan($loan);

        $this->assertEqualsWithDelta(0, (float) $loan->fresh()->provision_fee_amount, 0.01);
    }

    /** Persentase provisi produk tidak lagi berpengaruh sama sekali ke jurnalnya. */
    public function test_the_products_provision_percentage_no_longer_changes_the_journal(): void
    {
        [$loanNol, $kasNol] = $this->pinjaman(20_000_000, 0.0);
        [$loanLima, $kasLima] = $this->pinjaman(20_000_000, 5.0);

        $kreditNol = (float) $this->cairkan($loanNol)->lines->firstWhere('chart_of_account_id', $kasNol->id)->credit;
        $kreditLima = (float) $this->cairkan($loanLima)->lines->firstWhere('chart_of_account_id', $kasLima->id)->credit;

        $this->assertEqualsWithDelta(20_000_000, $kreditNol, 0.01);
        $this->assertEqualsWithDelta(20_000_000, $kreditLima, 0.01);
    }

    /** Jadwal angsuran memang selalu dihitung dari pokok penuh — pastikan tetap begitu. */
    public function test_the_schedule_still_covers_the_full_principal(): void
    {
        [$loan] = $this->pinjaman(30_000_000, 1.0);
        $this->cairkan($loan);

        $this->assertEqualsWithDelta(
            30_000_000,
            (float) $loan->fresh()->schedules()->sum('principal_amount'),
            1.0,
        );
    }
}
