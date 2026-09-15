<?php

namespace Tests\Feature\Loans;

use App\Exceptions\Loans\LoanApprovalException;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\User;
use App\Services\Loans\LoanApprovalService;
use App\Services\Settings\CashSettingsService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi produksi (sik-kppd.com, pinjaman 578): menekan "Setuju" membalas
 * HTTP 500 karena jurnal pencairan selalu mengkredit akun berkode `1101`
 * yang dipatok mati, padahal koperasi ini memakai bagan akunnya sendiri dan
 * sudah menjadikan `1101` akun header.
 *
 * Yang dikunci di sini adalah urutan penentuan akun kas pencairan: kas
 * pencairan yang ditetapkan di Pengaturan → Kas Cabang lebih dulu, lalu akun
 * kas cabang, lalu `1101`. Pencairan sengaja TIDAK memakai akun kas cabang
 * seperti angsuran — uangnya keluar dari kas kecil unit, sementara
 * angsurannya masuk lewat kas AO.
 */
class LoanDisbursementCashAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    private function postableAccount(string $code, string $name): ChartOfAccount
    {
        return ChartOfAccount::factory()->create([
            'code' => $code,
            'name' => $name,
            'type' => 'ASET',
            'normal_balance' => 'DEBIT',
            'statement' => 'NERACA',
            'is_postable' => true,
        ]);
    }

    private function loanInBranch(Branch $branch, User $creator): Loan
    {
        return Loan::factory()->create([
            'branch_id' => $branch->id,
            'created_by' => $creator->id,
            'required_approval_count' => 1,
            'principal_amount' => 30_000_000,
        ]);
    }

    /**
     * Pencairan mengkredit kas pencairan yang ditetapkan di Pengaturan → Kas
     * Cabang — BUKAN akun kas cabang pinjamannya.
     *
     * Uang pencairan keluar dari kas kecil unit (1101200 KAS KECIL (USP) di
     * koperasi ini), sementara angsurannya masuk lewat kas AO. Keduanya
     * memang akun yang berbeda. Cabang pinjaman juga bukan penunjuk yang
     * bisa dipakai: 131 dari 150 pinjaman tersimpan di cabang root "KPPD
     * Pusat".
     */
    public function test_disbursement_credits_the_configured_disbursement_account(): void
    {
        $creator = User::factory()->create();
        $approver = User::factory()->create();

        $kasPencairan = $this->postableAccount('1101200', 'KAS KECIL (USP)');
        $kasCabang = $this->postableAccount('1101500', 'KAS AO ASMAWI KSP');
        $branch = Branch::factory()->create(['cash_account_id' => $kasCabang->id]);
        $loan = $this->loanInBranch($branch, $creator);

        app(CashSettingsService::class)->update($kasPencairan->id, $creator->id);

        // `1101` sengaja dilumpuhkan seperti di produksi: kalau pencairan
        // masih menyentuhnya, uji ini gagal alih-alih diam-diam benar.
        ChartOfAccount::query()->where('code', '1101')->update(['is_postable' => false]);

        $result = app(LoanApprovalService::class)->approve($loan, $approver);

        $this->assertEquals('dicairkan', $result->status);

        $entry = JournalEntry::query()
            ->where('source_type', Loan::class)
            ->where('source_id', $loan->id)
            ->firstOrFail();

        $kasLine = $entry->lines->firstWhere('chart_of_account_id', $kasPencairan->id);

        $this->assertNotNull($kasLine, 'Jurnal pencairan harus mengkredit kas pencairan yang diatur.');
        $this->assertEquals(0, (float) $kasLine->debit);
        $this->assertGreaterThan(0, (float) $kasLine->credit);
        $this->assertEquals($entry->lines->sum('debit'), $entry->lines->sum('credit'));

        // Kas cabang dipakai angsuran, bukan pencairan — tidak boleh ikut tersentuh.
        $this->assertNull(
            $entry->lines->firstWhere('chart_of_account_id', $kasCabang->id),
            'Pencairan tidak boleh menyentuh akun kas cabang.',
        );
    }

    /** Selama kas pencairan belum diatur, perilakunya sama seperti sebelumnya. */
    public function test_without_a_configured_account_it_falls_back_to_the_branch_account(): void
    {
        $creator = User::factory()->create();
        $approver = User::factory()->create();

        $kasCabang = $this->postableAccount('1101500', 'KAS AO ASMAWI KSP');
        $branch = Branch::factory()->create(['cash_account_id' => $kasCabang->id]);
        $loan = $this->loanInBranch($branch, $creator);

        $result = app(LoanApprovalService::class)->approve($loan, $approver);

        $this->assertEquals('dicairkan', $result->status);

        $entry = JournalEntry::query()
            ->where('source_type', Loan::class)
            ->where('source_id', $loan->id)
            ->firstOrFail();

        $this->assertNotNull($entry->lines->firstWhere('chart_of_account_id', $kasCabang->id));
    }

    /** Cabang yang belum diatur akun kasnya tetap jatuh ke `1101` seperti dulu. */
    public function test_branch_without_cash_account_falls_back_to_1101(): void
    {
        $creator = User::factory()->create();
        $approver = User::factory()->create();

        $branch = Branch::factory()->create(['cash_account_id' => null]);
        $loan = $this->loanInBranch($branch, $creator);

        $result = app(LoanApprovalService::class)->approve($loan, $approver);

        $this->assertEquals('dicairkan', $result->status);

        $fallback = ChartOfAccount::query()->where('code', '1101')->firstOrFail();
        $entry = JournalEntry::query()
            ->where('source_type', Loan::class)
            ->where('source_id', $loan->id)
            ->firstOrFail();

        $this->assertNotNull($entry->lines->firstWhere('chart_of_account_id', $fallback->id));
    }

    /**
     * Kombinasi persis yang menjatuhkan produksi ke 500: cabang belum diatur
     * akun kasnya DAN `1101` sudah jadi akun header. Sekarang harus keluar
     * sebagai LoanApprovalException yang ditangkap controller, dan seluruh
     * transaksi mundur utuh.
     */
    public function test_unpostable_fallback_raises_approval_exception_and_rolls_back(): void
    {
        $creator = User::factory()->create();
        $approver = User::factory()->create();

        $branch = Branch::factory()->create(['cash_account_id' => null]);
        $loan = $this->loanInBranch($branch, $creator);

        ChartOfAccount::query()->where('code', '1101')->update(['is_postable' => false]);

        try {
            app(LoanApprovalService::class)->approve($loan, $approver);
            $this->fail('Pencairan seharusnya gagal karena akun kas tidak bisa diposting.');
        } catch (LoanApprovalException $exception) {
            $this->assertStringContainsString('1101', $exception->getMessage());
        }

        $loan->refresh();
        $this->assertEquals('diajukan', $loan->status);
        $this->assertNull($loan->disbursed_at);
        $this->assertDatabaseCount('loan_approvals', 0);
        $this->assertDatabaseCount('loan_schedules', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }
}
