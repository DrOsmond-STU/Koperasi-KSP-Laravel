<?php

namespace Tests\Feature\SecurityAudit;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\DepreciationBatchRun;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\OpeningBalanceBatch;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\FixedAsset\DepreciationEngine;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md AUD-05 (lock migrasi saldo awal -> siapa approve,
 * hasil rekonsiliasi, batch id tercatat) and AUD-06 (eksekusi batch
 * penyusutan -> run id, periode, jumlah aset diproses tercatat).
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_locking_opening_balance_batch_records_approver_and_reconciliation_result(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('bendahara');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $batch = OpeningBalanceBatch::query()->create([
            'branch_id' => Branch::factory()->create()->id,
            'cutoff_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $csv = "kode_akun,posisi,saldo,tanggal_saldo_awal\n"
            ."1101,Debit,50000000,2026-01-01\n"
            ."2101,Kredit,50000000,2026-01-01\n";
        $path = tempnam(sys_get_temp_dir(), 'ob_test_').'.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'import.csv', 'text/csv', null, true);

        $this->actingAs($user)->post(route('admin.saldo-awal.import', [$batch, 'coa']), [
            'file' => $file,
            'mode' => 'all_or_nothing',
        ]);

        $this->actingAs($user)->post(route('admin.saldo-awal.lock', $batch));

        $batch->refresh();
        $this->assertEquals('locked', $batch->status);
        $this->assertEquals($user->id, $batch->locked_by);
        $this->assertNotNull($batch->reconciliation_snapshot);
        $this->assertEquals(50000000.0, $batch->reconciliation_snapshot['coa_debit_total']);

        $log = AuditLog::query()
            ->where('auditable_type', OpeningBalanceBatch::class)
            ->where('auditable_id', $batch->id)
            ->where('action', 'update')
            ->latest()
            ->firstOrFail();

        $this->assertEquals($user->id, $log->actor_id);
        $this->assertArrayHasKey('reconciliation_snapshot', $log->after);
    }

    public function test_depreciation_batch_run_records_run_id_period_and_counts(): void
    {
        $category = FixedAssetCategory::factory()->create([
            'coa_asset_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_accumulated_depreciation_account_id' => ChartOfAccount::factory()->create()->id,
            'coa_depreciation_expense_account_id' => ChartOfAccount::factory()->create()->id,
        ]);
        FixedAsset::factory()->create([
            'fixed_asset_category_id' => $category->id,
            'acquisition_cost' => 12000000,
            'residual_value' => 1200000,
            'useful_life_months' => 24,
            'depreciation_method' => 'garis_lurus',
        ]);
        $user = User::factory()->create();

        app(DepreciationEngine::class)->run('2026-08', $user->id);

        $run = DepreciationBatchRun::query()->firstOrFail();
        $this->assertEquals('2026-08', $run->period);
        $this->assertEquals(1, $run->assets_processed);
        $this->assertEquals(0, $run->assets_skipped);
        $this->assertEquals($user->id, $run->triggered_by);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => DepreciationBatchRun::class,
            'auditable_id' => $run->id,
            'action' => 'create',
        ]);
    }
}
