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
}
