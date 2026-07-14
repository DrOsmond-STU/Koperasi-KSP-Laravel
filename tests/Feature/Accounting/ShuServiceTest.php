<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\ShuAllocationCategory;
use App\Models\User;
use App\Services\Accounting\JournalEngine;
use App\Services\Accounting\ShuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md RPT-08: alokasi SHU sesuai AD/ART, total 100%
 * teralokasi, hanya jenis anggota dengan counts_toward_shu = true diikutkan.
 */
class ShuServiceTest extends TestCase
{
    use RefreshDatabase;

    private function postShuIncome(int $branchId, int $userId, string $amount): void
    {
        $pendapatan = ChartOfAccount::factory()->create(['type' => 'PENDAPATAN', 'statement' => 'LABA_RUGI', 'normal_balance' => 'KREDIT']);
        $kas = ChartOfAccount::factory()->create(['normal_balance' => 'DEBIT']);

        app(JournalEngine::class)->post([
            'branch_id' => $branchId,
            'entry_date' => now()->toDateString(),
            'description' => 'Pendapatan uji',
            'created_by' => $userId,
            'lines' => [
                ['chart_of_account_id' => $kas->id, 'debit' => $amount, 'credit' => 0],
                ['chart_of_account_id' => $pendapatan->id, 'debit' => 0, 'credit' => $amount],
            ],
        ]);
    }

    public function test_allocations_sum_to_100_percent(): void
    {
        ShuAllocationCategory::query()->create(['name' => 'Cadangan', 'percentage' => 60, 'is_jasa_anggota' => false, 'is_active' => true]);
        ShuAllocationCategory::query()->create(['name' => 'Jasa Anggota', 'percentage' => 40, 'is_jasa_anggota' => true, 'is_active' => true]);

        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $this->postShuIncome($branch->id, $user->id, '1000000');

        $simulation = app(ShuService::class)->simulate($branch->id, (int) now()->format('Y'));

        $this->assertTrue($simulation['is_fully_allocated']);
        $this->assertEquals('100.00', $simulation['total_percentage']);
        $this->assertEquals('600000.00', $simulation['allocations']->firstWhere('category.name', 'Cadangan')['amount']);
        $this->assertEquals('400000.00', $simulation['allocations']->firstWhere('category.name', 'Jasa Anggota')['amount']);
    }

    public function test_only_shu_eligible_member_types_receive_a_share(): void
    {
        ShuAllocationCategory::query()->create(['name' => 'Jasa Anggota', 'percentage' => 100, 'is_jasa_anggota' => true, 'is_active' => true]);

        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $this->postShuIncome($branch->id, $user->id, '1000000');

        $eligibleType = MemberType::factory()->create(['counts_toward_shu' => true]);
        $ineligibleType = MemberType::factory()->create(['counts_toward_shu' => false]);

        $savingsProduct = SavingsProduct::factory()->create();

        $eligibleMember = Member::factory()->create(['branch_id' => $branch->id, 'member_type_id' => $eligibleType->id]);
        $ineligibleMember = Member::factory()->create(['branch_id' => $branch->id, 'member_type_id' => $ineligibleType->id]);

        SavingsAccount::query()->create([
            'branch_id' => $branch->id, 'member_id' => $eligibleMember->id, 'savings_product_id' => $savingsProduct->id,
            'account_number' => 'SA-ELIGIBLE', 'balance' => 500000, 'status' => 'aktif', 'opened_at' => now()->toDateString(),
        ]);
        SavingsAccount::query()->create([
            'branch_id' => $branch->id, 'member_id' => $ineligibleMember->id, 'savings_product_id' => $savingsProduct->id,
            'account_number' => 'SA-INELIGIBLE', 'balance' => 500000, 'status' => 'aktif', 'opened_at' => now()->toDateString(),
        ]);

        $simulation = app(ShuService::class)->simulate($branch->id, (int) now()->format('Y'));

        $this->assertCount(1, $simulation['member_shares']);
        $this->assertEquals($eligibleMember->id, $simulation['member_shares']->first()['member']->id);
        $this->assertEquals('1000000.00', $simulation['member_shares']->first()['share']);
    }

    public function test_jasa_anggota_is_distributed_proportional_to_savings_balance(): void
    {
        ShuAllocationCategory::query()->create(['name' => 'Jasa Anggota', 'percentage' => 100, 'is_jasa_anggota' => true, 'is_active' => true]);

        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $this->postShuIncome($branch->id, $user->id, '1000000');

        $type = MemberType::factory()->create(['counts_toward_shu' => true]);
        $savingsProduct = SavingsProduct::factory()->create();

        $memberA = Member::factory()->create(['branch_id' => $branch->id, 'member_type_id' => $type->id]);
        $memberB = Member::factory()->create(['branch_id' => $branch->id, 'member_type_id' => $type->id]);

        SavingsAccount::query()->create([
            'branch_id' => $branch->id, 'member_id' => $memberA->id, 'savings_product_id' => $savingsProduct->id,
            'account_number' => 'SA-A', 'balance' => 750000, 'status' => 'aktif', 'opened_at' => now()->toDateString(),
        ]);
        SavingsAccount::query()->create([
            'branch_id' => $branch->id, 'member_id' => $memberB->id, 'savings_product_id' => $savingsProduct->id,
            'account_number' => 'SA-B', 'balance' => 250000, 'status' => 'aktif', 'opened_at' => now()->toDateString(),
        ]);

        $simulation = app(ShuService::class)->simulate($branch->id, (int) now()->format('Y'));

        $shareA = $simulation['member_shares']->firstWhere('member.id', $memberA->id)['share'];
        $shareB = $simulation['member_shares']->firstWhere('member.id', $memberB->id)['share'];

        $this->assertEquals('750000.00', $shareA);
        $this->assertEquals('250000.00', $shareB);
    }
}
