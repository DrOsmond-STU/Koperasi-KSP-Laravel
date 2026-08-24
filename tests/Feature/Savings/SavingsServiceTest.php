<?php

namespace Tests\Feature\Savings;

use App\Exceptions\Savings\InsufficientBalanceException;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\User;
use App\Services\Savings\SavingsService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md §2 (LED-01, LED-07) and §2.4 (histori tarif tidak
 * retroaktif) for the Savings module (Task 1.12).
 */
class SavingsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The "1101 Kas" account (used as the default cash leg) must exist.
        $this->seed(ChartOfAccountsSeeder::class);
    }

    /** LED-01: a deposit produces a balanced journal entry and updates the cached balance. */
    public function test_deposit_creates_balanced_journal_and_updates_balance(): void
    {
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $user = User::factory()->create();

        $transaction = app(SavingsService::class)->deposit($account, 150000, $user->id);

        $this->assertEquals(150000, $transaction->amount);
        $this->assertEquals(150000, $account->fresh()->balance);

        $entry = $transaction->journalEntry;
        $this->assertEquals(
            $entry->lines->sum('debit'),
            $entry->lines->sum('credit'),
        );
    }

    /** LED-07: withdrawing more than the balance is rejected and posts no journal. */
    public function test_withdraw_more_than_balance_is_rejected(): void
    {
        $account = SavingsAccount::factory()->create(['balance' => 50000]);
        $user = User::factory()->create();

        $this->expectException(InsufficientBalanceException::class);

        try {
            app(SavingsService::class)->withdraw($account, 100000, $user->id);
        } finally {
            $this->assertEquals(50000, $account->fresh()->balance);
            $this->assertDatabaseCount('savings_transactions', 0);
        }
    }

    public function test_withdraw_within_balance_succeeds(): void
    {
        $account = SavingsAccount::factory()->create(['balance' => 100000]);
        $user = User::factory()->create();

        $transaction = app(SavingsService::class)->withdraw($account, 40000, $user->id);

        $this->assertEquals(60000, $account->fresh()->balance);
        $this->assertEquals(60000, $transaction->balance_after);
    }

    /**
     * Regresi untuk laporan staf 24 Agu 2026: "akun lawan kas nya salah...
     * ini harusnya masuk ke cabang KSP". Rekening dengan cabang yang sudah
     * dipetakan ke akun kasnya sendiri (lewat admin/pengaturan/kas-cabang)
     * resolusinya jatuh ke akun ITU — bukan lagi akun kas konsolidasi 1101
     * untuk semua cabang.
     *
     * Dites lewat cashAccount() langsung (bukan deposit()) — bcadd() di
     * deposit() gagal duluan di sandbox lokal ini (ext bcmath tidak
     * terpasang, gap lingkungan yang sudah ada sebelum perubahan ini),
     * jadi resolusi akun kas sendiri tidak sempat dites lewat jalur itu.
     */
    public function test_cash_account_resolves_to_the_branch_specific_account_when_configured(): void
    {
        $kasCabang = ChartOfAccount::factory()->create(['code' => '9990001', 'name' => 'Kas Cabang Uji']);
        $branch = Branch::factory()->create(['cash_account_id' => $kasCabang->id]);
        $account = SavingsAccount::factory()->create(['branch_id' => $branch->id]);

        $resolved = app(SavingsService::class)->cashAccount($account);

        $this->assertEquals($kasCabang->id, $resolved->id);
        $this->assertNotEquals('1101', $resolved->code);
    }

    /**
     * Kalau cabang rekening BELUM dipetakan tapi ada cabang "Unit Koperasi
     * Simpan Pinjam (KSP)" (kode 001) yang sudah dipetakan, resolusinya
     * jatuh ke akun kas KSP itu — bukan langsung ke 1101 konsolidasi.
     */
    public function test_cash_account_falls_back_to_ksp_branch_when_accounts_own_branch_has_none_configured(): void
    {
        $kasKsp = ChartOfAccount::factory()->create(['code' => '9990002', 'name' => 'Kas Cabang KSP Uji']);
        Branch::factory()->create(['code' => '001', 'cash_account_id' => $kasKsp->id]);
        $unmappedBranch = Branch::factory()->create(['cash_account_id' => null]);
        $account = SavingsAccount::factory()->create(['branch_id' => $unmappedBranch->id]);

        $resolved = app(SavingsService::class)->cashAccount($account);

        $this->assertEquals($kasKsp->id, $resolved->id);
    }

    /**
     * Tanpa argumen (dipakai untuk banner info di halaman Teller/Buka
     * Rekening SEBELUM rekening dipilih), resolusinya juga ke akun kas
     * cabang KSP — bukan 1101.
     */
    public function test_cash_account_with_no_argument_resolves_to_the_ksp_branch_default(): void
    {
        $kasKsp = ChartOfAccount::factory()->create(['code' => '9990003', 'name' => 'Kas Cabang KSP Uji']);
        Branch::factory()->create(['code' => '001', 'cash_account_id' => $kasKsp->id]);

        $resolved = app(SavingsService::class)->cashAccount();

        $this->assertEquals($kasKsp->id, $resolved->id);
    }

    /**
     * Jaring pengaman terakhir: kalau BAHKAN cabang KSP (001) belum ada/
     * belum dipetakan, tetap jatuh ke akun kas konsolidasi 1101 (tidak
     * error 500) — bukan perilaku ideal, tapi mencegah transaksi gagal
     * total.
     */
    public function test_cash_account_falls_back_to_consolidated_1101_when_no_branch_cash_account_configured_at_all(): void
    {
        $account = SavingsAccount::factory()->create();

        $resolved = app(SavingsService::class)->cashAccount($account);

        $this->assertEquals('1101', $resolved->code);
    }

    /** Rate history: a future rate change doesn't retroactively affect past resolution. */
    public function test_rate_history_is_not_retroactive(): void
    {
        $product = SavingsProduct::factory()->create();

        // factory already seeds one rate (2.5%) effective a year ago.
        $pastDate = now()->subMonths(6);
        $this->assertEquals(2.5, $product->rateAt($pastDate)->rate_percentage);

        // Add a new rate effective in the future.
        $product->rateHistory()->create([
            'rate_percentage' => 4.0,
            'effective_from' => now()->addMonth()->toDateString(),
        ]);

        // Past date resolution is unaffected by the future-dated rate.
        $this->assertEquals(2.5, (float) $product->rateAt($pastDate)->rate_percentage);
        // Today still resolves to the old rate (new one isn't effective yet).
        $this->assertEquals(2.5, (float) $product->rateAt(now())->rate_percentage);
        // But resolving "in the future" picks up the new rate.
        $this->assertEquals(4.0, (float) $product->rateAt(now()->addMonths(2))->rate_percentage);
    }

    public function test_tiered_rate_resolves_by_balance_bracket(): void
    {
        $product = SavingsProduct::factory()->create(['interest_method' => 'tiered']);

        $rate = $product->rateHistory()->create([
            'rate_percentage' => 1.0,
            'effective_from' => now()->subMonth()->toDateString(),
            'tiers' => [
                ['min_balance' => 0, 'max_balance' => 999999, 'rate_percentage' => 1.0],
                ['min_balance' => 1000000, 'max_balance' => null, 'rate_percentage' => 3.0],
            ],
        ]);

        $this->assertEquals(1.0, $rate->rateForBalance(500000));
        $this->assertEquals(3.0, $rate->rateForBalance(5000000));
    }
}
