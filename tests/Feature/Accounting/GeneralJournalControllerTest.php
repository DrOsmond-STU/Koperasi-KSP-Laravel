<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jurnal Umum — posting manual debit/kredit di luar simpan-pinjam, lewat
 * JournalEngine::post() (LED-01..08 sudah dijamin oleh JournalEngineTest —
 * test ini fokus ke lapisan HTTP/permission/form-nya sendiri).
 */
class GeneralJournalControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function bendahara(?Branch $branch = null): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('bendahara');
        UserBranchScope::query()->create([
            'user_id' => $user->id,
            'scope_type' => $branch ? 'single' : 'all',
            'single_branch_id' => $branch?->id,
        ]);

        return $user;
    }

    private function branch(): Branch
    {
        return Branch::query()->create(['code' => 'CBG-01', 'name' => 'Cabang Utama', 'is_active' => true]);
    }

    public function test_teller_role_cannot_access_general_journal(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)->get(route('admin.jurnal-umum.create'))->assertForbidden();
    }

    public function test_balanced_entry_is_posted_via_journal_engine(): void
    {
        $branch = $this->branch();
        $user = $this->bendahara($branch);

        $response = $this->actingAs($user)->post(route('admin.jurnal-umum.store'), [
            'entry_date' => '2026-07-16',
            'branch_id' => $branch->id,
            'description' => 'Bayar listrik kantor Juli 2026',
            'lines' => [
                ['chart_of_account_id' => ChartOfAccount::where('code', '5203')->value('id'), 'position' => 'debit', 'amount' => 500000],
                ['chart_of_account_id' => ChartOfAccount::where('code', '1101')->value('id'), 'position' => 'kredit', 'amount' => 500000],
            ],
        ]);

        $response->assertRedirect(route('admin.jurnal-umum.create'));
        $response->assertSessionHas('status');

        $entry = JournalEntry::query()->where('description', 'Bayar listrik kantor Juli 2026')->firstOrFail();
        $this->assertNull($entry->source_type);
        $this->assertNull($entry->reversal_of_entry_id);
        $this->assertCount(2, $entry->lines);
        $this->assertEquals(500000, (float) $entry->lines->sum('debit'));
        $this->assertEquals(500000, (float) $entry->lines->sum('credit'));
    }

    public function test_unbalanced_lines_are_rejected_at_form_level(): void
    {
        $branch = $this->branch();
        $user = $this->bendahara($branch);

        $response = $this->actingAs($user)->post(route('admin.jurnal-umum.store'), [
            'entry_date' => '2026-07-16',
            'branch_id' => $branch->id,
            'description' => 'Jurnal timpang',
            'lines' => [
                ['chart_of_account_id' => ChartOfAccount::where('code', '5203')->value('id'), 'position' => 'debit', 'amount' => 500000],
                ['chart_of_account_id' => ChartOfAccount::where('code', '1101')->value('id'), 'position' => 'kredit', 'amount' => 400000],
            ],
        ]);

        $response->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_non_postable_account_is_rejected(): void
    {
        $branch = $this->branch();
        $user = $this->bendahara($branch);
        $header = ChartOfAccount::where('code', '1100')->firstOrFail();
        $this->assertFalse($header->is_postable);

        $response = $this->actingAs($user)->post(route('admin.jurnal-umum.store'), [
            'entry_date' => '2026-07-16',
            'branch_id' => $branch->id,
            'description' => 'Posting ke akun header',
            'lines' => [
                ['chart_of_account_id' => $header->id, 'position' => 'debit', 'amount' => 100000],
                ['chart_of_account_id' => ChartOfAccount::where('code', '2101')->value('id'), 'position' => 'kredit', 'amount' => 100000],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.chart_of_account_id');
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_closed_period_is_rejected_with_friendly_message(): void
    {
        $branch = $this->branch();
        $user = $this->bendahara($branch);

        AccountingPeriod::query()->create([
            'branch_id' => $branch->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'status' => 'closed',
        ]);

        $response = $this->actingAs($user)->post(route('admin.jurnal-umum.store'), [
            'entry_date' => '2026-06-15',
            'branch_id' => $branch->id,
            'description' => 'Posting mundur ke periode tertutup',
            'lines' => [
                ['chart_of_account_id' => ChartOfAccount::where('code', '5203')->value('id'), 'position' => 'debit', 'amount' => 100000],
                ['chart_of_account_id' => ChartOfAccount::where('code', '1101')->value('id'), 'position' => 'kredit', 'amount' => 100000],
            ],
        ]);

        $response->assertRedirect(route('admin.jurnal-umum.create'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_single_branch_user_cannot_post_to_another_branch(): void
    {
        $ownBranch = $this->branch();
        $otherBranch = Branch::query()->create(['code' => 'CBG-02', 'name' => 'Cabang Lain', 'is_active' => true]);
        $user = $this->bendahara($ownBranch);

        $response = $this->actingAs($user)->post(route('admin.jurnal-umum.store'), [
            'entry_date' => '2026-07-16',
            'branch_id' => $otherBranch->id,
            'description' => 'Coba posting ke cabang lain',
            'lines' => [
                ['chart_of_account_id' => ChartOfAccount::where('code', '5203')->value('id'), 'position' => 'debit', 'amount' => 100000],
                ['chart_of_account_id' => ChartOfAccount::where('code', '1101')->value('id'), 'position' => 'kredit', 'amount' => 100000],
            ],
        ]);

        $response->assertSessionHasErrors('branch_id');
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_create_page_lists_recent_manual_entries_only(): void
    {
        $branch = $this->branch();
        $user = $this->bendahara($branch);

        $this->actingAs($user)->post(route('admin.jurnal-umum.store'), [
            'entry_date' => '2026-07-16',
            'branch_id' => $branch->id,
            'description' => 'Beban ATK kantor',
            'lines' => [
                ['chart_of_account_id' => ChartOfAccount::where('code', '5207')->value('id'), 'position' => 'debit', 'amount' => 150000],
                ['chart_of_account_id' => ChartOfAccount::where('code', '1101')->value('id'), 'position' => 'kredit', 'amount' => 150000],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('admin.jurnal-umum.create'));

        $response->assertOk();
        $response->assertSee('Beban ATK kantor');
    }
}
