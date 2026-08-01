<?php

namespace Tests\Feature\Retribution;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\RetributionType;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetributionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function petugasUpf(): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_upf');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function activateFullyAllocatedType(): RetributionType
    {
        return RetributionType::factory()->create([
            'percentage' => 100,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
    }

    public function test_full_transaction_flow_petugas_upf_can_record_and_see_it_in_dashboard(): void
    {
        $officer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        $this->activateFullyAllocatedType();

        $response = $this->actingAs($officer)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $branch->id,
            'payer_type' => 'umum',
            'payer_name' => 'Ibu Sari',
            'total_amount' => 75000,
            'payment_method' => 'tunai',
        ]);

        $response->assertRedirect(route('staf.retribusi-upf.index', ['tab' => 'transaksi']));
        $this->assertDatabaseHas('retribution_transactions', ['payer_name' => 'Ibu Sari', 'total_amount' => 75000]);

        $index = $this->actingAs($officer)->get(route('staf.retribusi-upf.index'));
        $index->assertOk();
        $index->assertSee('Ibu Sari');
    }

    public function test_role_without_permission_cannot_access_retribusi_upf(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('anggota');

        $this->actingAs($user)->get(route('staf.retribusi-upf.index'))->assertForbidden();
    }

    public function test_index_is_gated_not_just_store(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('anggota');

        $this->actingAs($user)->get(route('staf.retribusi-upf.index'))->assertForbidden();
        $this->actingAs($user)->post(route('staf.retribusi-upf.store'), [])->assertForbidden();
    }

    public function test_branch_scope_mismatch_is_rejected(): void
    {
        $ownBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_upf');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'single', 'single_branch_id' => $ownBranch->id]);
        $this->activateFullyAllocatedType();

        $response = $this->actingAs($user)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $otherBranch->id,
            'payer_type' => 'umum',
            'payer_name' => 'Ibu Sari',
            'total_amount' => 75000,
            'payment_method' => 'tunai',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('retribution_transactions', 0);
    }

    public function test_store_shows_domain_error_when_percentages_not_fully_allocated(): void
    {
        $officer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        RetributionType::factory()->create([
            'percentage' => 50,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($officer)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $branch->id,
            'payer_type' => 'umum',
            'payer_name' => 'Ibu Sari',
            'total_amount' => 75000,
            'payment_method' => 'tunai',
        ]);

        $response->assertRedirect(route('staf.retribusi-upf.index', ['tab' => 'transaksi']));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('retribution_transactions', 0);
    }

    private function recordTransaction(User $officer, Branch $branch): \App\Models\RetributionTransaction
    {
        $this->activateFullyAllocatedType();

        $this->actingAs($officer)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $branch->id,
            'payer_type' => 'umum',
            'payer_name' => 'Ibu Sari',
            'total_amount' => 75000,
            'payment_method' => 'tunai',
        ]);

        return \App\Models\RetributionTransaction::query()->where('payer_name', 'Ibu Sari')->firstOrFail();
    }

    public function test_creator_can_cancel_own_retribution_transaction(): void
    {
        $officer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        $trx = $this->recordTransaction($officer, $branch);

        $response = $this->actingAs($officer)->post(route('staf.retribusi-upf.cancel', $trx), [
            'reason' => 'Salah catat pembayar',
        ]);

        $response->assertRedirect(route('staf.retribusi-upf.index', ['tab' => 'transaksi']));
        $trx->refresh();
        $this->assertNotNull($trx->cancelled_at);
        $this->assertNotNull($trx->reversal_journal_entry_id);
        $this->assertDatabaseHas('journal_entries', [
            'id' => $trx->reversal_journal_entry_id,
            'reversal_of_entry_id' => $trx->journal_entry_id,
        ]);
    }

    public function test_other_officer_cannot_cancel_someone_elses_retribution_transaction(): void
    {
        $creator = $this->petugasUpf();
        $otherOfficer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        $trx = $this->recordTransaction($creator, $branch);

        $this->actingAs($otherOfficer)->post(route('staf.retribusi-upf.cancel', $trx), [
            'reason' => 'Coba batalkan punya orang lain',
        ])->assertForbidden();

        $this->assertNull($trx->fresh()->cancelled_at);
    }

    public function test_manajer_can_cancel_any_retribution_transaction(): void
    {
        $creator = $this->petugasUpf();
        $manajer = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $manajer->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $manajer->id, 'scope_type' => 'all']);
        $branch = Branch::factory()->create();
        $trx = $this->recordTransaction($creator, $branch);

        $this->actingAs($manajer)->post(route('staf.retribusi-upf.cancel', $trx), [
            'reason' => 'Koreksi oleh manajer',
        ])->assertRedirect(route('staf.retribusi-upf.index', ['tab' => 'transaksi']));

        $this->assertNotNull($trx->fresh()->cancelled_at);
    }
}
