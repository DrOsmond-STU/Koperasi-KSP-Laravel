<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Member;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceCoa;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\User;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Accounting\JournalEngine;
use App\Services\Savings\SavingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md LED-09: jumlah buku besar = jumlah rincian per
 * anggota/produk/cabang, selalu cocok (rekonsiliasi saldo simpanan
 * sebagai contoh konkret).
 */
class GeneralLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_running_balance_accumulates_correctly_for_a_debit_normal_account(): void
    {
        $cash = ChartOfAccount::factory()->create(['normal_balance' => 'DEBIT']);
        $contra = ChartOfAccount::factory()->create();
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        app(JournalEngine::class)->post([
            'branch_id' => $branch->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Setoran 1',
            'created_by' => $user->id,
            'lines' => [
                ['chart_of_account_id' => $cash->id, 'debit' => 100000, 'credit' => 0],
                ['chart_of_account_id' => $contra->id, 'debit' => 0, 'credit' => 100000],
            ],
        ]);
        app(JournalEngine::class)->post([
            'branch_id' => $branch->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Setoran 2',
            'created_by' => $user->id,
            'lines' => [
                ['chart_of_account_id' => $cash->id, 'debit' => 50000, 'credit' => 0],
                ['chart_of_account_id' => $contra->id, 'debit' => 0, 'credit' => 50000],
            ],
        ]);

        $lines = app(GeneralLedgerService::class)->linesFor($cash, $branch->id, now()->startOfMonth()->toDateString(), now()->toDateString());

        // Baris pertama = saldo awal sintetik (nol karena tidak ada mutasi
        // sebelum periode); dua berikutnya = mutasi periode.
        $this->assertCount(3, $lines);
        $this->assertTrue($lines->first()['is_opening']);
        $this->assertEquals('0.00', $lines->first()['running_balance']);
        $this->assertEquals('150000.00', $lines->last()['running_balance']);
    }

    /**
     * Bug regression: sebelum perbaikan, kartu buku besar mulai dari saldo
     * nol untuk akun Kas/Bank yang punya mutasi sebelum periode. Setelah
     * perbaikan, baris pertama = "Saldo Awal Periode" dengan saldo aktual
     * per (periodStart - 1 hari), dan running_balance mutasi periode
     * dihitung dari titik itu, bukan nol.
     */
    public function test_opening_balance_row_carries_over_activity_before_period(): void
    {
        $cash = ChartOfAccount::factory()->create(['normal_balance' => 'DEBIT']);
        $contra = ChartOfAccount::factory()->create();
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        // Mutasi bulan lalu — harus jadi bagian saldo awal, bukan baris
        // mutasi.
        app(JournalEngine::class)->post([
            'branch_id' => $branch->id,
            'entry_date' => now()->subMonth()->toDateString(),
            'description' => 'Setoran bulan lalu',
            'created_by' => $user->id,
            'lines' => [
                ['chart_of_account_id' => $cash->id, 'debit' => 1000000, 'credit' => 0],
                ['chart_of_account_id' => $contra->id, 'debit' => 0, 'credit' => 1000000],
            ],
        ]);
        // Mutasi bulan ini — masuk ke daftar mutasi, running_balance
        // dihitung dari saldo awal 1.000.000.
        app(JournalEngine::class)->post([
            'branch_id' => $branch->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Setoran bulan ini',
            'created_by' => $user->id,
            'lines' => [
                ['chart_of_account_id' => $cash->id, 'debit' => 250000, 'credit' => 0],
                ['chart_of_account_id' => $contra->id, 'debit' => 0, 'credit' => 250000],
            ],
        ]);

        $lines = app(GeneralLedgerService::class)->linesFor($cash, $branch->id, now()->startOfMonth()->toDateString(), now()->toDateString());

        $this->assertCount(2, $lines, 'Saldo awal + 1 mutasi bulan ini');
        $this->assertTrue($lines->first()['is_opening']);
        $this->assertEquals('1000000.00', $lines->first()['running_balance'], 'Saldo awal periode = mutasi sebelum bulan ini');
        $this->assertEquals('Setoran bulan ini', $lines->last()['description']);
        $this->assertEquals('1250000.00', $lines->last()['running_balance'], 'Saldo akhir = saldo awal + mutasi bulan ini');
    }

    /**
     * Regresi untuk "Perhitungan neraca juga harus memperhitungkan saldo
     * awal dari migrasi smik": sebuah batch DRAFT (belum pernah dikunci,
     * jadi belum pernah dijurnal — OpeningBalanceLockService::lock()
     * belum jalan) masih harus terlihat di balanceFor(), karena Neraca
     * membaca lewat balanceFor(), bukan langsung dari OpeningBalanceCoa.
     * Sebelum perbaikan ini, akun tanpa mutasi baru sejak migrasi akan
     * tampak bersaldo nol di Neraca — persis keluhan pengguna.
     */
    public function test_balance_for_includes_migrated_opening_balance_even_when_batch_is_draft(): void
    {
        $cash = ChartOfAccount::factory()->create(['normal_balance' => 'DEBIT']);
        $branch = Branch::factory()->create();

        $batch = OpeningBalanceBatch::query()->create([
            'branch_id' => $branch->id,
            'cutoff_date' => '2026-07-31',
            'status' => 'draft',
        ]);

        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $cash->id,
            'position' => 'debit',
            'amount' => 5000000,
        ]);

        // Tidak ada transaksi apa pun setelah migrasi.
        $balance = app(GeneralLedgerService::class)->balanceFor($cash, $branch->id, '2026-08-31');

        $this->assertEquals('5000000.00', $balance);
    }

    /**
     * Kebalikan dari test di atas: batch yang SUDAH dikunci (jurnal
     * pembukaannya sudah diposting, source_type=OpeningBalanceBatch)
     * tidak boleh terhitung dua kali — sekali dari journal_lines, sekali
     * lagi dari OpeningBalanceCoa.
     */
    public function test_balance_for_does_not_double_count_locked_batch_opening_journal(): void
    {
        $cash = ChartOfAccount::factory()->create(['normal_balance' => 'DEBIT']);
        $contra = ChartOfAccount::factory()->create();
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        $batch = OpeningBalanceBatch::query()->create([
            'branch_id' => $branch->id,
            'cutoff_date' => '2026-07-31',
            'status' => 'locked',
            'locked_by' => $user->id,
            'locked_at' => now(),
        ]);

        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $cash->id,
            'position' => 'debit',
            'amount' => 5000000,
        ]);

        // Simulasi OpeningBalanceLockService::postOpeningJournal().
        app(JournalEngine::class)->post([
            'branch_id' => $branch->id,
            'entry_date' => '2026-07-31',
            'description' => 'Jurnal pembukaan migrasi saldo awal (batch #'.$batch->id.')',
            'created_by' => $user->id,
            'source' => $batch,
            'lines' => [
                ['chart_of_account_id' => $cash->id, 'debit' => 5000000, 'credit' => 0],
                ['chart_of_account_id' => $contra->id, 'debit' => 0, 'credit' => 5000000],
            ],
        ]);

        $balance = app(GeneralLedgerService::class)->balanceFor($cash, $branch->id, '2026-08-31');

        // Bukan 10.000.000 (5jt OpeningBalanceCoa + 5jt jurnal yang sudah
        // diposting) — harus tetap 5jt persis.
        $this->assertEquals('5000000.00', $balance);
    }

    /**
     * Saldo migrasi tidak boleh "berlaku surut" sebelum cutoff_date-nya
     * sendiri — melihat saldo per tanggal sebelum migrasi terjadi harus
     * tetap nol, bukan ikut memuat migrasi yang secara kronologis belum
     * "terjadi" pada tanggal itu.
     */
    public function test_balance_for_excludes_migration_before_its_own_cutoff_date(): void
    {
        $cash = ChartOfAccount::factory()->create(['normal_balance' => 'DEBIT']);
        $branch = Branch::factory()->create();

        $batch = OpeningBalanceBatch::query()->create([
            'branch_id' => $branch->id,
            'cutoff_date' => '2026-07-31',
            'status' => 'draft',
        ]);

        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $cash->id,
            'position' => 'debit',
            'amount' => 5000000,
        ]);

        $balance = app(GeneralLedgerService::class)->balanceFor($cash, $branch->id, '2026-06-30');

        $this->assertEquals('0.00', $balance);
    }

    public function test_reconcile_savings_liability_matches_ledger_with_account_details(): void
    {
        $branch = Branch::factory()->create();
        $liabilityAccount = ChartOfAccount::factory()->create(['code' => '2101', 'normal_balance' => 'KREDIT']);
        $expenseAccount = ChartOfAccount::factory()->create();
        ChartOfAccount::factory()->create(['code' => '1101']);

        $product = SavingsProduct::factory()->create([
            'coa_liability_account_id' => $liabilityAccount->id,
            'coa_interest_expense_account_id' => $expenseAccount->id,
        ]);
        $member = Member::factory()->create(['branch_id' => $branch->id]);
        $user = User::factory()->create();

        $account = SavingsAccount::query()->create([
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'savings_product_id' => $product->id,
            'account_number' => 'SA-TEST-001',
            'balance' => 0,
            'status' => 'aktif',
            'opened_at' => now()->toDateString(),
        ]);

        app(SavingsService::class)->deposit($account, 200000, $user->id);

        $result = app(GeneralLedgerService::class)->reconcileSavingsLiability($liabilityAccount, $branch->id);

        $this->assertTrue($result['matches']);
        $this->assertEquals('200000.00', $result['ledger_balance']);
        $this->assertEquals('200000.00', $result['detail_total']);
    }
}
