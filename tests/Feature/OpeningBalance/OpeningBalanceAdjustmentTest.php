<?php

namespace Tests\Feature\OpeningBalance;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\OpeningBalanceAdjustment;
use App\Models\OpeningBalanceBatch;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Ubah / Koreksi Saldo Awal Neraca — batch draft (ubah baris COA langsung)
 * dan batch terkunci (jurnal koreksi + riwayat, re-auth kata sandi).
 */
class OpeningBalanceAdjustmentTest extends TestCase
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
        $user = User::factory()->create([
            'two_factor_confirmed_at' => now(),
            'password' => Hash::make('rahasia123'),
        ]);
        $user->assignRole('bendahara');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::query()->where('code', $code)->firstOrFail();
    }

    /** Kas 1101 = 50jt (D), Kewajiban 2101 = 50jt (K). */
    private function batch(string $status = 'draft'): OpeningBalanceBatch
    {
        $batch = OpeningBalanceBatch::query()->create([
            'branch_id' => Branch::factory()->create()->id,
            'cutoff_date' => '2026-01-01',
            'status' => 'draft',
        ]);

        $batch->coaLines()->create(['chart_of_account_id' => $this->account('1101')->id, 'position' => 'debit', 'amount' => 50000000]);
        $batch->coaLines()->create(['chart_of_account_id' => $this->account('2101')->id, 'position' => 'kredit', 'amount' => 50000000]);

        return $batch;
    }

    private function lockedBatch(User $user): OpeningBalanceBatch
    {
        $batch = $this->batch();
        $this->actingAs($user)->post(route('admin.saldo-awal.lock', $batch))->assertSessionHas('status');

        return $batch->fresh();
    }

    public function test_form_lists_neraca_accounts_with_current_balance(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();

        $this->actingAs($user)->get(route('admin.saldo-awal.koreksi', $batch))
            ->assertOk()
            ->assertSee('Ubah Saldo Awal Neraca')
            ->assertSee('1101')
            ->assertSee('50.000.000,00')
            ->assertDontSee('name="balances['.$this->account('3150')->id.']"', false);
    }

    public function test_draft_batch_rewrites_coa_lines_and_books_difference_to_balancing_account(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();
        $kas = $this->account('1101');
        $shuDitahan = $this->account('3160');

        $this->actingAs($user)->post(route('admin.saldo-awal.koreksi.store', $batch), [
            'balances' => [$kas->id => '50250000'],
            'balancing_account_id' => $shuDitahan->id,
        ])->assertRedirect(route('admin.saldo-awal.show', $batch));

        $lines = $batch->coaLines()->get()->keyBy('chart_of_account_id');
        $this->assertEquals(50250000, (float) $lines[$kas->id]->amount);
        $this->assertSame('debit', $lines[$kas->id]->position);
        $this->assertEquals(250000, (float) $lines[$shuDitahan->id]->amount);
        $this->assertSame('kredit', $lines[$shuDitahan->id]->position);
        $this->assertSame(0, JournalEntry::query()->count());
    }

    public function test_draft_coa_row_can_be_edited_inline(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();
        $row = $batch->coaLines()->first();

        $this->actingAs($user)->put(route('admin.saldo-awal.row.coa.update', [$batch, $row->id]), [
            'chart_of_account_id' => $row->chart_of_account_id,
            'position' => 'debit',
            'amount' => '49000000',
        ])->assertRedirect(route('admin.saldo-awal.show', $batch));

        $this->assertEquals(49000000, (float) $row->fresh()->amount);
    }

    public function test_locked_batch_posts_correction_journal_and_keeps_original_lines(): void
    {
        $user = $this->bendahara();
        $batch = $this->lockedBatch($user);
        $kas = $this->account('1101');
        $kewajiban = $this->account('2101');
        $shuDitahan = $this->account('3160');

        $response = $this->actingAs($user)->post(route('admin.saldo-awal.koreksi.store', $batch), [
            'balances' => [
                $kas->id => '49500000',        // turun 500rb → Kredit 500rb
                $kewajiban->id => '50200000',  // naik 200rb → Kredit 200rb
            ],
            'balancing_account_id' => $shuDitahan->id, // penyeimbang Debit 700rb
            'reason' => 'Selisih rekonsiliasi neraca sistem lama',
            'password' => 'rahasia123',
        ]);

        $response->assertRedirect(route('admin.saldo-awal.koreksi', $batch));
        $response->assertSessionHas('status');

        // Baris saldo awal asli tidak berubah.
        $this->assertEquals(50000000, (float) $batch->coaLines()->where('chart_of_account_id', $kas->id)->value('amount'));

        $adjustment = OpeningBalanceAdjustment::query()->with('lines')->sole();
        $this->assertSame('2026-01-01', $adjustment->entry_date->toDateString());
        $this->assertEquals(700000, (float) $adjustment->total_debit);
        $this->assertEquals(700000, (float) $adjustment->total_credit);

        $journal = JournalEntry::query()->findOrFail($adjustment->journal_entry_id);
        $this->assertSame(OpeningBalanceAdjustment::class, $journal->source_type);
        $byAccount = $journal->lines->keyBy('chart_of_account_id');
        $this->assertEquals(500000, (float) $byAccount[$kas->id]->credit);
        $this->assertEquals(200000, (float) $byAccount[$kewajiban->id]->credit);
        $this->assertEquals(700000, (float) $byAccount[$shuDitahan->id]->debit);

        $kasLine = $adjustment->lines->firstWhere('chart_of_account_id', $kas->id);
        $this->assertEquals(50000000, (float) $kasLine->balance_before);
        $this->assertEquals(49500000, (float) $kasLine->balance_after);

        // Saldo awal efektif berikutnya sudah memperhitungkan koreksi ini.
        $this->actingAs($user)->get(route('admin.saldo-awal.koreksi', $batch))
            ->assertOk()
            ->assertSee('49.500.000,00')
            ->assertSee('Selisih rekonsiliasi neraca sistem lama');
    }

    public function test_locked_batch_rejects_unbalanced_change_without_balancing_account(): void
    {
        $user = $this->bendahara();
        $batch = $this->lockedBatch($user);

        $this->actingAs($user)->post(route('admin.saldo-awal.koreksi.store', $batch), [
            'balances' => [$this->account('1101')->id => '50100000'],
            'reason' => 'Tes',
            'password' => 'rahasia123',
        ])->assertSessionHas('error');

        $this->assertSame(0, OpeningBalanceAdjustment::query()->count());
    }

    public function test_locked_batch_requires_correct_password(): void
    {
        $user = $this->bendahara();
        $batch = $this->lockedBatch($user);

        $this->actingAs($user)->post(route('admin.saldo-awal.koreksi.store', $batch), [
            'balances' => [$this->account('1101')->id => '50100000'],
            'balancing_account_id' => $this->account('3160')->id,
            'reason' => 'Tes',
            'password' => 'salah',
        ])->assertSessionHas('error');

        $this->assertSame(0, OpeningBalanceAdjustment::query()->count());
    }

    public function test_no_changes_is_rejected(): void
    {
        $user = $this->bendahara();
        $batch = $this->batch();

        $this->actingAs($user)->post(route('admin.saldo-awal.koreksi.store', $batch), [
            'balances' => [$this->account('1101')->id => '50000000'],
        ])->assertSessionHas('error');
    }

    public function test_teller_cannot_adjust_opening_balance(): void
    {
        $teller = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $teller->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $teller->id, 'scope_type' => 'all']);
        $batch = $this->batch();

        $this->actingAs($teller)->post(route('admin.saldo-awal.koreksi.store', $batch), [
            'balances' => [$this->account('1101')->id => '1'],
        ])->assertForbidden();
    }
}
