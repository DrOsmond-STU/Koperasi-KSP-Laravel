<?php

namespace Tests\Feature\Reporting;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceCoa;
use App\Models\User;
use App\Services\Accounting\JournalEngine;
use App\Services\Reporting\FinancialReportEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md RPT-01 (Neraca seimbang), RPT-02 (Laba Rugi sesuai
 * akumulasi jurnal periode), RPT-03 (Arus Kas konsisten dengan buku
 * besar), RPT-04 (ganti basis tidak mengubah angka dasar), RPT-05
 * (konsolidasi mengeliminasi RAK), RPT-09 (periode kosong -> empty state).
 */
class FinancialReportEngineTest extends TestCase
{
    use RefreshDatabase;

    private function postEntry(int $branchId, int $userId, string $date, array $lines, string $description = 'Uji'): void
    {
        app(JournalEngine::class)->post([
            'branch_id' => $branchId,
            'entry_date' => $date,
            'description' => $description,
            'created_by' => $userId,
            'lines' => $lines,
        ]);
    }

    public function test_neraca_is_balanced_including_opening_balance_style_entries(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $aset = ChartOfAccount::factory()->create(['type' => 'ASET', 'statement' => 'NERACA', 'normal_balance' => 'DEBIT']);
        $ekuitas = ChartOfAccount::factory()->create(['type' => 'EKUITAS', 'statement' => 'NERACA', 'normal_balance' => 'KREDIT']);

        // Simulates an opening-balance migration entry: Dr Aset, Cr Ekuitas.
        $this->postEntry($branch->id, $user->id, '2026-01-01', [
            ['chart_of_account_id' => $aset->id, 'debit' => 5000000, 'credit' => 0],
            ['chart_of_account_id' => $ekuitas->id, 'debit' => 0, 'credit' => 5000000],
        ], 'Saldo awal migrasi');

        $neraca = app(FinancialReportEngine::class)->neraca($branch->id, '2026-01-31');

        $this->assertTrue($neraca['is_balanced']);
        $this->assertEquals('5000000.00', $neraca['total_aset']);
        $this->assertTrue($neraca['has_data']);
    }

    /**
     * Regresi langsung untuk keluhan pengguna: "kalau transaksi kosong
     * berarti saldo yang ditampilkan adalah saldo awal ... Perhitungan
     * neraca juga harus memperhitungkan saldo awal dari migrasi smik."
     * Sebuah batch migrasi DRAFT (belum dikunci lewat
     * OpeningBalanceLockService, jadi belum pernah dijurnal) harus tetap
     * terlihat di Neraca — GeneralLedgerService::balanceFor() yang
     * menjembataninya (lihat GeneralLedgerServiceTest untuk cakupan
     * detail per-skenario draft/locked/sebelum-cutoff).
     */
    public function test_neraca_reflects_migrated_opening_balance_with_zero_transactions(): void
    {
        $branch = Branch::factory()->create();
        $aset = ChartOfAccount::factory()->create(['type' => 'ASET', 'statement' => 'NERACA', 'normal_balance' => 'DEBIT']);
        $ekuitas = ChartOfAccount::factory()->create(['type' => 'EKUITAS', 'statement' => 'NERACA', 'normal_balance' => 'KREDIT']);

        $batch = OpeningBalanceBatch::query()->create([
            'branch_id' => $branch->id,
            'cutoff_date' => '2026-07-31',
            'status' => 'draft',
        ]);

        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $aset->id,
            'position' => 'debit',
            'amount' => 5000000,
        ]);
        OpeningBalanceCoa::query()->create([
            'opening_balance_batch_id' => $batch->id,
            'chart_of_account_id' => $ekuitas->id,
            'position' => 'kredit',
            'amount' => 5000000,
        ]);

        // Tidak ada satu jurnal pun diposting — cabang ini belum ada
        // transaksi baru sama sekali sejak migrasi.
        $neraca = app(FinancialReportEngine::class)->neraca($branch->id, '2026-08-31');

        $this->assertTrue($neraca['is_balanced']);
        $this->assertEquals('5000000.00', $neraca['total_aset']);
        $this->assertEquals('5000000.00', $neraca['total_ekuitas']);
        $this->assertTrue($neraca['has_data']);
    }

    public function test_neraca_eliminates_rak_account_only_when_consolidated(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $user = User::factory()->create();
        $rak = ChartOfAccount::factory()->create(['code' => '1132', 'type' => 'ASET', 'statement' => 'NERACA', 'normal_balance' => 'DEBIT']);
        $contra = ChartOfAccount::factory()->create(['type' => 'EKUITAS', 'statement' => 'NERACA', 'normal_balance' => 'KREDIT']);

        $this->postEntry($branchA->id, $user->id, now()->toDateString(), [
            ['chart_of_account_id' => $rak->id, 'debit' => 1000000, 'credit' => 0],
            ['chart_of_account_id' => $contra->id, 'debit' => 0, 'credit' => 1000000],
        ]);
        $this->postEntry($branchB->id, $user->id, now()->toDateString(), [
            ['chart_of_account_id' => $contra->id, 'debit' => 1000000, 'credit' => 0],
            ['chart_of_account_id' => $rak->id, 'debit' => 0, 'credit' => 1000000],
        ]);

        $perBranch = app(FinancialReportEngine::class)->neraca($branchA->id, now()->toDateString());
        $rakRowPerBranch = collect($perBranch['rows'])->firstWhere('account.code', '1132');
        $this->assertFalse($rakRowPerBranch['eliminated']);
        $this->assertEquals('1000000.00', $rakRowPerBranch['balance']);

        $consolidated = app(FinancialReportEngine::class)->neraca(null, now()->toDateString());
        $rakRowConsolidated = collect($consolidated['rows'])->firstWhere('account.code', '1132');
        $this->assertTrue($rakRowConsolidated['eliminated']);
        $this->assertEquals('0.00', $rakRowConsolidated['balance']);
    }

    public function test_laba_rugi_reflects_period_pendapatan_and_beban(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $kas = ChartOfAccount::factory()->create(['normal_balance' => 'DEBIT']);
        $pendapatan = ChartOfAccount::factory()->create(['type' => 'PENDAPATAN', 'statement' => 'LABA_RUGI', 'normal_balance' => 'KREDIT']);
        $beban = ChartOfAccount::factory()->create(['type' => 'BEBAN', 'statement' => 'LABA_RUGI', 'normal_balance' => 'DEBIT']);

        $this->postEntry($branch->id, $user->id, '2026-05-10', [
            ['chart_of_account_id' => $kas->id, 'debit' => 300000, 'credit' => 0],
            ['chart_of_account_id' => $pendapatan->id, 'debit' => 0, 'credit' => 300000],
        ]);
        $this->postEntry($branch->id, $user->id, '2026-05-15', [
            ['chart_of_account_id' => $beban->id, 'debit' => 100000, 'credit' => 0],
            ['chart_of_account_id' => $kas->id, 'debit' => 0, 'credit' => 100000],
        ]);

        $labaRugi = app(FinancialReportEngine::class)->labaRugi($branch->id, '2026-05-01', '2026-05-31');

        $this->assertEquals('300000.00', $labaRugi['total_pendapatan']);
        $this->assertEquals('100000.00', $labaRugi['total_beban']);
        $this->assertEquals('200000.00', $labaRugi['shu']);
    }

    public function test_arus_kas_is_consistent_with_opening_plus_net_equals_closing(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $kas = ChartOfAccount::factory()->create(['code' => '1101', 'normal_balance' => 'DEBIT']);
        $contra = ChartOfAccount::factory()->create();

        $this->postEntry($branch->id, $user->id, '2026-06-01', [
            ['chart_of_account_id' => $kas->id, 'debit' => 500000, 'credit' => 0],
            ['chart_of_account_id' => $contra->id, 'debit' => 0, 'credit' => 500000],
        ]);
        $this->postEntry($branch->id, $user->id, '2026-06-15', [
            ['chart_of_account_id' => $contra->id, 'debit' => 200000, 'credit' => 0],
            ['chart_of_account_id' => $kas->id, 'debit' => 0, 'credit' => 200000],
        ]);

        $arusKas = app(FinancialReportEngine::class)->arusKas($branch->id, '2026-06-01', '2026-06-30');

        $this->assertTrue($arusKas['is_consistent']);
        $this->assertEquals('300000.00', $arusKas['closing_balance']);
    }

    public function test_switching_basis_changes_nothing_but_the_label(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $aset = ChartOfAccount::factory()->create(['type' => 'ASET', 'statement' => 'NERACA', 'normal_balance' => 'DEBIT']);
        $ekuitas = ChartOfAccount::factory()->create(['type' => 'EKUITAS', 'statement' => 'NERACA', 'normal_balance' => 'KREDIT']);

        $this->postEntry($branch->id, $user->id, now()->toDateString(), [
            ['chart_of_account_id' => $aset->id, 'debit' => 750000, 'credit' => 0],
            ['chart_of_account_id' => $ekuitas->id, 'debit' => 0, 'credit' => 750000],
        ]);

        $engine = app(FinancialReportEngine::class);
        $sakEp = $engine->neraca($branch->id, now()->toDateString(), 'sak_ep');
        $sakEmkm = $engine->neraca($branch->id, now()->toDateString(), 'sak_emkm');

        $this->assertEquals($sakEp['total_aset'], $sakEmkm['total_aset']);
        $this->assertEquals('sak_ep', $sakEp['basis']);
        $this->assertEquals('sak_emkm', $sakEmkm['basis']);
    }

    public function test_empty_period_reports_no_data_instead_of_misleading_zero(): void
    {
        $branch = Branch::factory()->create();

        $labaRugi = app(FinancialReportEngine::class)->labaRugi($branch->id, '2020-01-01', '2020-01-31');

        $this->assertFalse($labaRugi['has_data']);
    }

    public function test_report_for_a_closed_period_is_cached(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $aset = ChartOfAccount::factory()->create(['type' => 'ASET', 'statement' => 'NERACA', 'normal_balance' => 'DEBIT']);
        $ekuitas = ChartOfAccount::factory()->create(['type' => 'EKUITAS', 'statement' => 'NERACA', 'normal_balance' => 'KREDIT']);

        $this->postEntry($branch->id, $user->id, '2026-02-10', [
            ['chart_of_account_id' => $aset->id, 'debit' => 1000000, 'credit' => 0],
            ['chart_of_account_id' => $ekuitas->id, 'debit' => 0, 'credit' => 1000000],
        ]);

        AccountingPeriod::query()->create([
            'branch_id' => $branch->id,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'status' => 'closed',
            'closed_by' => $user->id,
            'closed_at' => now(),
        ]);

        $cacheKey = "financial_report:neraca:{$branch->id}:2026-02-28:sak_ep";
        $this->assertFalse(Cache::has($cacheKey));

        $neraca = app(FinancialReportEngine::class)->neraca($branch->id, '2026-02-28');

        $this->assertTrue(Cache::has($cacheKey));
        $this->assertEquals('1000000.00', $neraca['total_aset']);
    }

    public function test_report_for_an_open_period_is_never_cached(): void
    {
        $branch = Branch::factory()->create();

        app(FinancialReportEngine::class)->neraca($branch->id, '2026-03-31');

        $this->assertFalse(Cache::has("financial_report:neraca:{$branch->id}:2026-03-31:sak_ep"));
    }
}
