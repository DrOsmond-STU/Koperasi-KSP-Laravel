<?php

namespace Tests\Feature\Retribution;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Member;
use App\Models\MemberType;
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

    /**
     * Jenis retribusi "umum" (percentage = 100%, satu jenis per transaksi
     * umum). Dipakai transaksi Umum untuk menunjuk ke akun pendapatan
     * yang spesifik tanpa pembagian.
     */
    private function umumType(): RetributionType
    {
        return RetributionType::factory()->create([
            'percentage' => 100,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
    }

    /**
     * Set jenis retribusi "split" (percentage < 100, jumlahnya = 100).
     * Dipakai transaksi Anggota — pembagian otomatis via
     * RetributionSplitCalculator.
     *
     * @return array<int, RetributionType>
     */
    private function activateSplitTypes(): array
    {
        return [
            RetributionType::factory()->create([
                'code' => 'SPLIT-A',
                'percentage' => 60,
                'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
                'is_active' => true,
                'sort_order' => 1,
            ]),
            RetributionType::factory()->create([
                'code' => 'SPLIT-B',
                'percentage' => 40,
                'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
                'is_active' => true,
                'sort_order' => 2,
            ]),
        ];
    }

    public function test_full_transaction_flow_petugas_upf_can_record_and_see_it_in_dashboard(): void
    {
        $officer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        $type = $this->umumType();

        $response = $this->actingAs($officer)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $branch->id,
            'payer_type' => 'umum',
            'payer_name' => 'Ibu Sari',
            'retribution_type_id' => $type->id,
            'total_amount' => 75000,
            'payment_method' => 'tunai',
        ]);

        $response->assertRedirect(route('staf.retribusi-upf.index', ['tab' => 'transaksi']));
        $response->assertSessionMissing('error');
        $this->assertDatabaseHas('retribution_transactions', [
            'payer_name' => 'Ibu Sari',
            'total_amount' => 75000,
            'retribution_type_id' => $type->id,
        ]);
        // Umum → satu line, percentage_applied = 100.
        $this->assertDatabaseHas('retribution_transaction_lines', [
            'retribution_type_id' => $type->id,
            'percentage_applied' => 100,
            'amount' => 75000,
        ]);

        $index = $this->actingAs($officer)->get(route('staf.retribusi-upf.index'));
        $index->assertOk();
        $index->assertSee('Ibu Sari');
    }

    public function test_umum_transaction_requires_jenis_retribusi(): void
    {
        $officer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        $this->umumType();

        $response = $this->actingAs($officer)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $branch->id,
            'payer_type' => 'umum',
            'payer_name' => 'Ibu Sari',
            'total_amount' => 75000,
            'payment_method' => 'tunai',
        ]);

        $response->assertSessionHasErrors('retribution_type_id');
        $this->assertDatabaseCount('retribution_transactions', 0);
    }

    public function test_umum_transaction_rejects_split_type(): void
    {
        $officer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        [$splitA] = $this->activateSplitTypes();

        $response = $this->actingAs($officer)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $branch->id,
            'payer_type' => 'umum',
            'payer_name' => 'Ibu Sari',
            'retribution_type_id' => $splitA->id,
            'total_amount' => 75000,
            'payment_method' => 'tunai',
        ]);

        $response->assertRedirect(route('staf.retribusi-upf.index', ['tab' => 'transaksi']));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('retribution_transactions', 0);
    }

    public function test_anggota_transaction_splits_across_split_types(): void
    {
        $officer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        [$splitA, $splitB] = $this->activateSplitTypes();
        $memberType = MemberType::query()->firstOrCreate(['code' => 'KIOS'], ['name' => 'Anggota Kios', 'is_active' => true]);
        $member = Member::factory()->create(['member_type_id' => $memberType->id, 'status' => 'aktif', 'branch_id' => $branch->id]);

        $response = $this->actingAs($officer)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $branch->id,
            'payer_type' => 'anggota',
            'member_id' => $member->id,
            'total_amount' => 100000,
            'payment_method' => 'tunai',
        ]);

        $response->assertRedirect(route('staf.retribusi-upf.index', ['tab' => 'transaksi']));
        $response->assertSessionMissing('error');
        // 60% + 40% = Rp 60.000 + Rp 40.000
        $this->assertDatabaseHas('retribution_transaction_lines', [
            'retribution_type_id' => $splitA->id,
            'amount' => 60000,
        ]);
        $this->assertDatabaseHas('retribution_transaction_lines', [
            'retribution_type_id' => $splitB->id,
            'amount' => 40000,
        ]);
        $this->assertDatabaseHas('retribution_transactions', [
            'member_id' => $member->id,
            'retribution_type_id' => null,
            'total_amount' => 100000,
        ]);
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
        $type = $this->umumType();

        $response = $this->actingAs($user)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $otherBranch->id,
            'payer_type' => 'umum',
            'payer_name' => 'Ibu Sari',
            'retribution_type_id' => $type->id,
            'total_amount' => 75000,
            'payment_method' => 'tunai',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('retribution_transactions', 0);
    }

    public function test_anggota_store_shows_domain_error_when_split_percentages_not_fully_allocated(): void
    {
        $officer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        RetributionType::factory()->create([
            'percentage' => 50,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
        $memberType = MemberType::query()->firstOrCreate(['code' => 'KIOS'], ['name' => 'Anggota Kios', 'is_active' => true]);
        $member = Member::factory()->create(['member_type_id' => $memberType->id, 'status' => 'aktif', 'branch_id' => $branch->id]);

        $response = $this->actingAs($officer)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $branch->id,
            'payer_type' => 'anggota',
            'member_id' => $member->id,
            'total_amount' => 75000,
            'payment_method' => 'tunai',
        ]);

        $response->assertRedirect(route('staf.retribusi-upf.index', ['tab' => 'transaksi']));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('retribution_transactions', 0);
    }

    public function test_cash_counter_account_is_kas_ao_ridwan_when_available(): void
    {
        $officer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        $type = $this->umumType();
        // KAS AO RIDWAN (UPF) — di-seed lewat migrasi
        // 2026_08_20_100000_add_kas_ao_ridwan_upf_to_chart_of_accounts.php
        $cash = ChartOfAccount::query()->where('code', '1101600')->first();
        $this->assertNotNull($cash, 'Migration seharusnya membuat akun 1101600.');

        $this->actingAs($officer)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $branch->id,
            'payer_type' => 'umum',
            'payer_name' => 'Ibu Sari',
            'retribution_type_id' => $type->id,
            'total_amount' => 75000,
            'payment_method' => 'tunai',
        ])->assertRedirect();

        // Baris jurnal debit menunjuk ke akun 1101600.
        $this->assertDatabaseHas('journal_lines', [
            'chart_of_account_id' => $cash->id,
            'debit' => 75000,
        ]);
    }

    private function recordTransaction(User $officer, Branch $branch): \App\Models\RetributionTransaction
    {
        $type = $this->umumType();

        $this->actingAs($officer)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $branch->id,
            'payer_type' => 'umum',
            'payer_name' => 'Ibu Sari',
            'retribution_type_id' => $type->id,
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
