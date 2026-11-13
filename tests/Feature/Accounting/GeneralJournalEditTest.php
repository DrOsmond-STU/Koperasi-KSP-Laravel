<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Accounting\JournalEngine;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Edit Jurnal Umum = balik entri lama + posting pengganti (satu transaksi),
 * dan Kartu Buku Besar menyembunyikan pasangan jurnal yang dibatalkan kecuali
 * diminta "tampilkan_dibatalkan".
 */
class GeneralJournalEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole($role);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function branch(): Branch
    {
        return Branch::query()->create(['code' => 'CBG-01', 'name' => 'Cabang Utama', 'is_active' => true]);
    }

    private function akun(string $code): int
    {
        return (int) ChartOfAccount::where('code', $code)->value('id');
    }

    private function postManual(User $user, Branch $branch, string $description = 'Bayar listrik kantor', float $amount = 250000): JournalEntry
    {
        $this->actingAs($user)->post(route('admin.jurnal-umum.store'), [
            'entry_date' => now()->toDateString(),
            'branch_id' => $branch->id,
            'description' => $description,
            'lines' => [
                ['chart_of_account_id' => $this->akun('5203'), 'position' => 'debit', 'amount' => $amount],
                ['chart_of_account_id' => $this->akun('1101'), 'position' => 'kredit', 'amount' => $amount],
            ],
        ]);

        return JournalEntry::query()->where('description', $description)->firstOrFail();
    }

    private function dataKoreksi(Branch $branch, array $override = []): array
    {
        return array_merge([
            'entry_date' => now()->toDateString(),
            'branch_id' => $branch->id,
            'description' => 'Bayar listrik kantor (dikoreksi)',
            'reason' => 'Salah nominal',
            'password' => 'password',
            'lines' => [
                ['chart_of_account_id' => $this->akun('5203'), 'position' => 'debit', 'amount' => 300000],
                ['chart_of_account_id' => $this->akun('1101'), 'position' => 'kredit', 'amount' => 300000],
            ],
        ], $override);
    }

    public function test_bendahara_can_open_edit_form_for_manual_entry(): void
    {
        $branch = $this->branch();
        $user = $this->user('bendahara');
        $entry = $this->postManual($user, $branch);

        $this->actingAs($user)->get(route('admin.jurnal-umum.edit', $entry))
            ->assertOk()
            ->assertSee('Bayar listrik kantor');
    }

    public function test_update_reverses_original_and_posts_replacement(): void
    {
        $branch = $this->branch();
        $user = $this->user('bendahara');
        $entry = $this->postManual($user, $branch);

        $this->actingAs($user)->put(route('admin.jurnal-umum.update', $entry), $this->dataKoreksi($branch))
            ->assertRedirect(route('admin.jurnal-umum.create'))
            ->assertSessionHas('status');

        $pembalik = JournalEntry::query()->where('reversal_of_entry_id', $entry->id)->first();
        $this->assertNotNull($pembalik, 'Jurnal pembalik harus dibuat.');
        $this->assertSame($entry->entry_date->toDateString(), $pembalik->entry_date->toDateString());

        $pengganti = JournalEntry::query()->where('description', 'Bayar listrik kantor (dikoreksi)')->firstOrFail();
        $this->assertNull($pengganti->reversal_of_entry_id);
        $this->assertEqualsWithDelta(300000, (float) $pengganti->lines()->sum('debit'), 0.001);

        // Entri asal tidak diubah: baris & nominalnya utuh.
        $this->assertEqualsWithDelta(250000, (float) $entry->fresh()->lines()->sum('debit'), 0.001);
    }

    public function test_wrong_password_changes_nothing(): void
    {
        $branch = $this->branch();
        $user = $this->user('bendahara');
        $entry = $this->postManual($user, $branch);
        $jumlahAwal = JournalEntry::query()->count();

        $this->actingAs($user)->put(route('admin.jurnal-umum.update', $entry), $this->dataKoreksi($branch, ['password' => 'salah']))
            ->assertRedirect(route('admin.jurnal-umum.edit', $entry))
            ->assertSessionHas('error');

        $this->assertSame($jumlahAwal, JournalEntry::query()->count());
        $this->assertFalse($entry->reversals()->exists());
    }

    public function test_reason_and_password_are_required(): void
    {
        $branch = $this->branch();
        $user = $this->user('bendahara');
        $entry = $this->postManual($user, $branch);

        $this->actingAs($user)->put(route('admin.jurnal-umum.update', $entry), $this->dataKoreksi($branch, ['reason' => '', 'password' => '']))
            ->assertSessionHasErrors(['reason', 'password']);

        $this->assertFalse($entry->reversals()->exists());
    }

    public function test_already_reversed_entry_cannot_be_edited(): void
    {
        $branch = $this->branch();
        $user = $this->user('bendahara');
        $entry = $this->postManual($user, $branch);
        app(JournalEngine::class)->reverse($entry, 'Batal', $user->id);
        $jumlahAwal = JournalEntry::query()->count();

        $this->actingAs($user)->get(route('admin.jurnal-umum.edit', $entry))
            ->assertRedirect(route('admin.jurnal-umum.create'))
            ->assertSessionHas('error');

        $this->actingAs($user)->put(route('admin.jurnal-umum.update', $entry), $this->dataKoreksi($branch))
            ->assertRedirect(route('admin.jurnal-umum.create'))
            ->assertSessionHas('error');

        $this->assertSame($jumlahAwal, JournalEntry::query()->count());
    }

    public function test_role_without_adjust_permission_cannot_edit(): void
    {
        $branch = $this->branch();
        $bendahara = $this->user('bendahara');
        $entry = $this->postManual($bendahara, $branch);
        $teller = $this->user('teller');

        $this->actingAs($teller)->get(route('admin.jurnal-umum.edit', $entry))->assertForbidden();
        $this->actingAs($teller)->put(route('admin.jurnal-umum.update', $entry), $this->dataKoreksi($branch))->assertForbidden();

        $this->assertFalse($entry->reversals()->exists());
    }

    public function test_ledger_hides_cancelled_pair_by_default_and_keeps_running_balance(): void
    {
        $branch = $this->branch();
        $user = $this->user('bendahara');
        $tetap = $this->postManual($user, $branch, 'Bayar air kantor', 100000);
        $batal = $this->postManual($user, $branch, 'Salah input', 500000);
        app(JournalEngine::class)->reverse($batal, 'Salah input', $user->id);

        $akun = ChartOfAccount::where('code', '5203')->firstOrFail();
        $hari = now()->toDateString();
        $service = app(GeneralLedgerService::class);

        $default = $service->linesFor($akun, null, $hari, $hari);
        $semua = $service->linesFor($akun, null, $hari, $hari, true);

        // Default: saldo awal + 1 baris (Bayar air). Dengan dibatalkan: + asal + pembalik.
        $this->assertCount(2, $default);
        $this->assertCount(4, $semua);
        $this->assertEqualsWithDelta(
            (float) $default->last()['running_balance'],
            (float) $semua->last()['running_balance'],
            0.001,
            'Menyembunyikan pasangan batal tidak boleh mengubah saldo akhir.'
        );
        $this->assertNotNull($tetap);
    }
}
