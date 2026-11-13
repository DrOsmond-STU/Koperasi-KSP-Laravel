<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\CashCategory;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Cash\TellerCashService;
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

    private function postManual(User $user, Branch $branch, string $description = 'Bayar listrik kantor'): JournalEntry
    {
        $this->actingAs($user)->post(route('admin.jurnal-umum.store'), [
            'entry_date' => now()->toDateString(),
            'branch_id' => $branch->id,
            'description' => $description,
            'lines' => [
                ['chart_of_account_id' => ChartOfAccount::where('code', '5203')->value('id'), 'position' => 'debit', 'amount' => 250000.75],
                ['chart_of_account_id' => ChartOfAccount::where('code', '1101')->value('id'), 'position' => 'kredit', 'amount' => 250000.75],
            ],
        ]);

        return JournalEntry::query()->where('description', $description)->firstOrFail();
    }

    public function test_manual_entry_can_be_cancelled_with_reason_and_password(): void
    {
        $branch = $this->branch();
        $user = $this->bendahara($branch);
        $entry = $this->postManual($user, $branch);

        $this->actingAs($user)->get(route('admin.jurnal-umum.create'))
            ->assertOk()
            ->assertSee(route('admin.jurnal-umum.cancel', $entry), false);

        $response = $this->actingAs($user)->post(route('admin.jurnal-umum.cancel', $entry), [
            'reason' => 'Salah input akun',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.jurnal-umum.create'));
        $response->assertSessionHas('status');

        $reversal = JournalEntry::query()->where('reversal_of_entry_id', $entry->id)->firstOrFail();
        $this->assertStringContainsString('Salah input akun', $reversal->description);
        $this->assertEquals((float) $entry->lines->sum('debit'), (float) $reversal->lines->sum('credit'));
        $this->assertEquals((float) $entry->lines->sum('credit'), (float) $reversal->lines->sum('debit'));

        // Entri asal tidak diubah (append-only) dan halaman menampilkannya sebagai Dibatalkan.
        $this->assertCount(2, $entry->fresh()->lines);
        $this->actingAs($user)->get(route('admin.jurnal-umum.create'))
            ->assertSee('Dibatalkan')
            ->assertDontSee(route('admin.jurnal-umum.cancel', $entry), false);
    }

    public function test_wrong_password_does_not_cancel(): void
    {
        $branch = $this->branch();
        $user = $this->bendahara($branch);
        $entry = $this->postManual($user, $branch);

        $this->actingAs($user)->post(route('admin.jurnal-umum.cancel', $entry), [
            'reason' => 'Salah input',
            'password' => 'bukan-password',
        ])->assertSessionHas('error');

        $this->assertDatabaseMissing('journal_entries', ['reversal_of_entry_id' => $entry->id]);
    }

    public function test_missing_reason_is_rejected(): void
    {
        $branch = $this->branch();
        $user = $this->bendahara($branch);
        $entry = $this->postManual($user, $branch);

        $this->actingAs($user)->post(route('admin.jurnal-umum.cancel', $entry), [
            'reason' => '',
            'password' => 'password',
        ])->assertSessionHas('error', 'Alasan pembatalan wajib diisi.');

        $this->assertDatabaseMissing('journal_entries', ['reversal_of_entry_id' => $entry->id]);
    }

    public function test_entry_cannot_be_cancelled_twice(): void
    {
        $branch = $this->branch();
        $user = $this->bendahara($branch);
        $entry = $this->postManual($user, $branch);

        $payload = ['reason' => 'Salah input', 'password' => 'password'];
        $this->actingAs($user)->post(route('admin.jurnal-umum.cancel', $entry), $payload)->assertSessionHas('status');
        $this->actingAs($user)->post(route('admin.jurnal-umum.cancel', $entry), $payload)->assertSessionHas('error');

        $this->assertSame(1, JournalEntry::query()->where('reversal_of_entry_id', $entry->id)->count());
    }

    public function test_teller_cash_journal_cannot_be_cancelled_from_general_journal(): void
    {
        $branch = $this->branch();
        $user = $this->bendahara($branch);
        $category = CashCategory::factory()->create(['coa_account_id' => ChartOfAccount::where('code', '4101')->value('id') ?? ChartOfAccount::factory()]);
        $cash = ChartOfAccount::where('code', '1101')->firstOrFail();

        $tellerTrx = app(TellerCashService::class)->record($category, 75000, $branch->id, $user->id, 'Setoran kas lain-lain', $cash);
        $entry = JournalEntry::query()->findOrFail($tellerTrx->journal_entry_id);
        $this->assertNull($entry->source_type);

        // Jurnal Kas Teller tidak tampil di riwayat Jurnal Umum sama sekali.
        $this->actingAs($user)->get(route('admin.jurnal-umum.create'))
            ->assertDontSee('Setoran kas lain-lain')
            ->assertDontSee(route('admin.jurnal-umum.cancel', $entry), false);

        $this->actingAs($user)->post(route('admin.jurnal-umum.cancel', $entry), [
            'reason' => 'Coba batalkan jurnal kas teller',
            'password' => 'password',
        ])->assertSessionHas('error');

        $this->assertDatabaseMissing('journal_entries', ['reversal_of_entry_id' => $entry->id]);
    }

    public function test_creator_without_adjust_permission_can_only_cancel_own_entry(): void
    {
        $branch = $this->branch();
        $owner = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $owner->givePermissionTo('jurnal.create');
        UserBranchScope::query()->create(['user_id' => $owner->id, 'scope_type' => 'all']);
        $other = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $other->givePermissionTo('jurnal.create');
        UserBranchScope::query()->create(['user_id' => $other->id, 'scope_type' => 'all']);

        $entry = $this->postManual($owner, $branch, 'Jurnal milik pembuat');

        $this->actingAs($other)->post(route('admin.jurnal-umum.cancel', $entry), [
            'reason' => 'Bukan milik saya',
            'password' => 'password',
        ])->assertForbidden();

        $this->actingAs($owner)->post(route('admin.jurnal-umum.cancel', $entry), [
            'reason' => 'Salah input',
            'password' => 'password',
        ])->assertSessionHas('status');

        $this->assertSame(1, JournalEntry::query()->where('reversal_of_entry_id', $entry->id)->count());
    }
}
