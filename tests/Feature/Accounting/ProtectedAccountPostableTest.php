<?php

namespace Tests\Feature\Accounting;

use App\Models\ChartOfAccount;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Akun inti tidak boleh dijadikan akun header.
 *
 * Akun pada ChartOfAccount::PROTECTED_CODES dijurnal langsung oleh service
 * lewat pencarian kode harfiah, jadi mengubahnya jadi akun header (
 * is_postable = false) melumpuhkan pencairan pinjaman, setoran/penarikan
 * simpanan, kas teller, POS, dan retribusi sekaligus.
 *
 * Bukan skenario karangan: 15 Sep 2026 akun kas '1101' di produksi memang
 * dijadikan akun header, dan setiap persetujuan pinjaman balas HTTP 500.
 * Kodenya sudah lama dikunci, keterpostingannya belum.
 */
class ProtectedAccountPostableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('admin_sistem');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    /** @return array<string, mixed> */
    private function payload(ChartOfAccount $account, array $ubah = []): array
    {
        return array_merge([
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type,
            'group' => $account->group,
            'normal_balance' => $account->normal_balance,
            'is_postable' => '1',
            'parent_code' => $account->parent_code,
            'statement' => $account->statement,
            'notes' => $account->notes,
        ], $ubah);
    }

    private function akunInti(): ChartOfAccount
    {
        return ChartOfAccount::query()
            ->whereIn('code', ChartOfAccount::PROTECTED_CODES)
            ->where('is_postable', true)
            ->firstOrFail();
    }

    /** Kotak centang yang tidak dicentang memang tidak mengirim apa pun — itu jalur yang harus tertangkap. */
    public function test_unchecking_postable_on_a_core_account_is_rejected(): void
    {
        $akun = $this->akunInti();

        $this->actingAs($this->admin())
            ->put(route('admin.master.chart-of-accounts.update', $akun), $this->payload($akun, ['is_postable' => null]))
            ->assertSessionHasErrors('is_postable');

        $this->assertTrue($akun->fresh()->is_postable, 'Akun inti harus tetap bisa diposting.');
    }

    public function test_sending_postable_false_on_a_core_account_is_rejected(): void
    {
        $akun = $this->akunInti();

        $this->actingAs($this->admin())
            ->put(route('admin.master.chart-of-accounts.update', $akun), $this->payload($akun, ['is_postable' => '0']))
            ->assertSessionHasErrors('is_postable');

        $this->assertTrue($akun->fresh()->is_postable);
    }

    public function test_a_core_account_can_still_be_edited_while_staying_postable(): void
    {
        $akun = $this->akunInti();

        $this->actingAs($this->admin())
            ->put(route('admin.master.chart-of-accounts.update', $akun), $this->payload($akun, ['name' => 'Kas Konsolidasi']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Kas Konsolidasi', $akun->fresh()->name);
    }

    /** Akun biasa tetap bebas dijadikan akun header — penguncian ini hanya untuk akun inti. */
    public function test_an_ordinary_account_may_become_a_header(): void
    {
        $akun = ChartOfAccount::query()
            ->whereNotIn('code', ChartOfAccount::PROTECTED_CODES)
            ->where('is_postable', true)
            ->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.master.chart-of-accounts.update', $akun), $this->payload($akun, ['is_postable' => null]))
            ->assertSessionHasNoErrors();

        $this->assertFalse((bool) $akun->fresh()->is_postable);
    }
}
