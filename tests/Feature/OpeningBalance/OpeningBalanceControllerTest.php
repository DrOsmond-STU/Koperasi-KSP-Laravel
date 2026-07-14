<?php

namespace Tests\Feature\OpeningBalance;

use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\Member;
use App\Models\OpeningBalanceBatch;
use App\Models\SavingsProduct;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md OB-03, OB-04, OB-05, OB-06, OB-07 at the HTTP layer.
 */
class OpeningBalanceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function bendahara(): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('bendahara');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function csvFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ob_test_').'.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'import.csv', 'text/csv', null, true);
    }

    private function batch(): OpeningBalanceBatch
    {
        return OpeningBalanceBatch::query()->create([
            'branch_id' => Branch::factory()->create()->id,
            'cutoff_date' => '2026-01-01',
            'status' => 'draft',
        ]);
    }

    /** OB-03: all_or_nothing rejects the whole file when any row has errors. */
    public function test_all_or_nothing_mode_commits_nothing_when_any_row_fails(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();
        Member::factory()->create(['member_number' => 'AGT-0001']);
        SavingsProduct::factory()->create(['code' => 'SIM-A']);

        $csv = "kode_anggota,kode_produk_simpanan,no_rekening,saldo_awal,tanggal_saldo_awal,keterangan\n"
            ."AGT-0001,SIM-A,,1000000,2026-01-01,\n"
            ."AGT-TIDAK-ADA,SIM-A,,500000,2026-01-01,\n";

        $response = $this->actingAs($user)->post(
            route('admin.saldo-awal.import', [$batch, 'savings']),
            ['file' => $this->csvFile($csv), 'mode' => 'all_or_nothing'],
        );

        $response->assertRedirect(route('admin.saldo-awal.show', $batch));
        $this->assertDatabaseCount('opening_balance_savings', 0);
    }

    /** OB-04: partial mode commits only the valid rows. */
    public function test_partial_mode_commits_valid_rows_and_ignores_errors(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();
        Member::factory()->create(['member_number' => 'AGT-0001']);
        SavingsProduct::factory()->create(['code' => 'SIM-A']);

        $csv = "kode_anggota,kode_produk_simpanan,no_rekening,saldo_awal,tanggal_saldo_awal,keterangan\n"
            ."AGT-0001,SIM-A,,1000000,2026-01-01,\n"
            ."AGT-TIDAK-ADA,SIM-A,,500000,2026-01-01,\n";

        $response = $this->actingAs($user)->post(
            route('admin.saldo-awal.import', [$batch, 'savings']),
            ['file' => $this->csvFile($csv), 'mode' => 'partial'],
        );

        $response->assertRedirect(route('admin.saldo-awal.show', $batch));
        $this->assertDatabaseCount('opening_balance_savings', 1);
    }

    /** OB-05: lock is rejected while the batch is not balanced. */
    public function test_lock_is_rejected_when_not_balanced(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();

        $csv = "kode_akun,posisi,saldo,tanggal_saldo_awal\n"
            ."1101,Debit,50000000,2026-01-01\n"; // no matching credit row -> unbalanced

        $this->actingAs($user)->post(
            route('admin.saldo-awal.import', [$batch, 'coa']),
            ['file' => $this->csvFile($csv), 'mode' => 'all_or_nothing'],
        );

        $response = $this->actingAs($user)->post(route('admin.saldo-awal.lock', $batch));

        $response->assertSessionHas('error');
        $this->assertEquals('draft', $batch->fresh()->status);
    }

    /** OB-06: lock succeeds once balanced, posting the opening journal. */
    public function test_lock_succeeds_when_balanced_and_posts_opening_journal(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();

        $csv = "kode_akun,posisi,saldo,tanggal_saldo_awal\n"
            ."1101,Debit,50000000,2026-01-01\n"
            ."2101,Kredit,50000000,2026-01-01\n";

        $this->actingAs($user)->post(
            route('admin.saldo-awal.import', [$batch, 'coa']),
            ['file' => $this->csvFile($csv), 'mode' => 'all_or_nothing'],
        );

        $response = $this->actingAs($user)->post(route('admin.saldo-awal.lock', $batch));

        $response->assertSessionHas('status');
        $this->assertEquals('locked', $batch->fresh()->status);

        $journalEntry = JournalEntry::query()
            ->where('source_type', OpeningBalanceBatch::class)
            ->where('source_id', $batch->id)
            ->firstOrFail();
        $this->assertEquals(50000000, (float) $journalEntry->lines->sum('debit'));
        $this->assertEquals(50000000, (float) $journalEntry->lines->sum('credit'));
    }

    /** OB-07: once locked, no further import is accepted for this batch. */
    public function test_locked_batch_rejects_further_import(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();

        $csv = "kode_akun,posisi,saldo,tanggal_saldo_awal\n"
            ."1101,Debit,1000000,2026-01-01\n"
            ."2101,Kredit,1000000,2026-01-01\n";

        $this->actingAs($user)->post(
            route('admin.saldo-awal.import', [$batch, 'coa']),
            ['file' => $this->csvFile($csv), 'mode' => 'all_or_nothing'],
        );
        $this->actingAs($user)->post(route('admin.saldo-awal.lock', $batch));

        $this->assertEquals('locked', $batch->fresh()->status);

        $response = $this->actingAs($user)->post(
            route('admin.saldo-awal.import', [$batch, 'coa']),
            ['file' => $this->csvFile($csv), 'mode' => 'all_or_nothing'],
        );

        $response->assertNotFound();
    }

    public function test_teller_role_cannot_access_opening_balance_module(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');

        $this->actingAs($user)
            ->get(route('admin.saldo-awal.index'))
            ->assertForbidden();
    }
}
