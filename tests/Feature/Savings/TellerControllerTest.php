<?php

namespace Tests\Feature\Savings;

use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md E2E-02: Teller setor simpanan → preview jurnal →
 * simpan → muncul di feed.
 */
class TellerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function teller(): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    public function test_teller_can_preview_then_commit_a_deposit(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);

        $preview = $this->actingAs($teller)->post(route('staf.teller.preview'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'amount' => 200000,
        ]);

        $preview->assertOk();
        $preview->assertSee('1101'); // kas account visible in the preview lines
        $preview->assertSee($account->savingsProduct->liabilityAccount->code);

        $store = $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'amount' => 200000,
        ]);

        $store->assertRedirect(route('staf.teller.create'));
        $this->assertEquals(200000, $account->fresh()->balance);
        $this->assertDatabaseHas('savings_transactions', [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'amount' => 200000,
        ]);
    }

    public function test_teller_feed_shows_todays_transaction(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);

        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'amount' => 75000,
        ]);

        $response = $this->actingAs($teller)->get(route('staf.teller.create'));

        $response->assertOk();
        $response->assertSee($account->member->name);
    }

    public function test_anggota_role_cannot_access_teller_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('anggota');

        $this->actingAs($user)
            ->get(route('staf.teller.create'))
            ->assertForbidden();
    }

    public function test_creator_can_cancel_own_deposit_and_balance_reverts(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);

        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'amount' => 150000,
        ]);
        $tx = \App\Models\SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();

        $response = $this->actingAs($teller)->post(route('staf.teller.cancel', $tx), [
            'reason' => 'Salah input nominal',
        ]);

        $response->assertRedirect(route('staf.teller.create'));
        $this->assertEquals(0, $account->fresh()->balance);
        $tx->refresh();
        $this->assertNotNull($tx->cancelled_at);
        $this->assertEquals('Salah input nominal', $tx->cancellation_reason);
        $this->assertNotNull($tx->reversal_journal_entry_id);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $tx->reversal_journal_entry_id,
            'reversal_of_entry_id' => $tx->journal_entry_id,
        ]);
    }

    public function test_other_teller_cannot_cancel_someone_elses_transaction(): void
    {
        $creator = $this->teller();
        $otherTeller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);

        $this->actingAs($creator)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'amount' => 100000,
        ]);
        $tx = \App\Models\SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();

        $this->actingAs($otherTeller)->post(route('staf.teller.cancel', $tx), [
            'reason' => 'Coba batalkan punya orang lain',
        ])->assertForbidden();

        $this->assertEquals(100000, $account->fresh()->balance);
        $this->assertNull($tx->fresh()->cancelled_at);
    }

    public function test_manajer_can_cancel_another_tellers_transaction(): void
    {
        $creator = $this->teller();
        $manajer = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $manajer->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $manajer->id, 'scope_type' => 'all']);

        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $this->actingAs($creator)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'amount' => 50000,
        ]);
        $tx = \App\Models\SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();

        $this->actingAs($manajer)->post(route('staf.teller.cancel', $tx), [
            'reason' => 'Koreksi oleh manajer',
        ])->assertRedirect(route('staf.teller.create'));

        $this->assertEquals(0, $account->fresh()->balance);
    }

    public function test_cancelling_an_already_cancelled_transaction_is_rejected(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'amount' => 60000,
        ]);
        $tx = \App\Models\SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();

        $this->actingAs($teller)->post(route('staf.teller.cancel', $tx), ['reason' => 'Pertama']);

        $this->expectException(\App\Exceptions\Savings\TransactionAlreadyCancelledException::class);
        app(\App\Services\Savings\SavingsService::class)->reverseTransaction($tx->fresh(), 'Kedua', $teller->id);
    }

    public function test_teller_can_approve_a_members_withdrawal_request(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 500000]);
        $requester = User::factory()->create();
        $withdrawalRequest = app(\App\Services\Savings\SavingsWithdrawalRequestService::class)
            ->request($account, 100000, $requester->id);

        $response = $this->actingAs($teller)->post(route('staf.teller.decide-withdrawal', $withdrawalRequest), [
            'decision' => 'setuju',
        ]);

        $response->assertRedirect(route('staf.teller.create'));
        $this->assertEquals('disetujui', $withdrawalRequest->fresh()->status);
        $this->assertEquals(400000, (float) $account->fresh()->balance);
    }

    public function test_teller_can_reject_a_members_withdrawal_request(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 500000]);
        $requester = User::factory()->create();
        $withdrawalRequest = app(\App\Services\Savings\SavingsWithdrawalRequestService::class)
            ->request($account, 100000, $requester->id);

        $response = $this->actingAs($teller)->post(route('staf.teller.decide-withdrawal', $withdrawalRequest), [
            'decision' => 'tolak',
            'notes' => 'Data anggota tidak sesuai',
        ]);

        $response->assertRedirect(route('staf.teller.create'));
        $this->assertEquals('ditolak', $withdrawalRequest->fresh()->status);
        $this->assertEquals(500000, (float) $account->fresh()->balance);
    }

    public function test_role_without_simpanan_approve_permission_cannot_decide_a_withdrawal(): void
    {
        $petugasKredit = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $petugasKredit->assignRole('petugas_kredit');
        UserBranchScope::query()->create(['user_id' => $petugasKredit->id, 'scope_type' => 'all']);

        $account = SavingsAccount::factory()->create(['balance' => 500000]);
        $requester = User::factory()->create();
        $withdrawalRequest = app(\App\Services\Savings\SavingsWithdrawalRequestService::class)
            ->request($account, 100000, $requester->id);

        $this->actingAs($petugasKredit)->post(route('staf.teller.decide-withdrawal', $withdrawalRequest), [
            'decision' => 'setuju',
        ])->assertForbidden();

        $this->assertEquals('menunggu', $withdrawalRequest->fresh()->status);
    }
}
