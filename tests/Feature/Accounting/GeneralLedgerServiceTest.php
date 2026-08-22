<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Member;
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
