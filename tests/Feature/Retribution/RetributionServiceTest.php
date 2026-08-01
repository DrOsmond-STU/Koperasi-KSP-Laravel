<?php

namespace Tests\Feature\Retribution;

use App\Exceptions\Retribution\RetributionException;
use App\Models\ChartOfAccount;
use App\Models\Member;
use App\Models\RetributionType;
use App\Models\User;
use App\Services\Retribution\RetributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetributionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function cashAccount(): ChartOfAccount
    {
        return ChartOfAccount::factory()->create(['code' => '1101']);
    }

    private function twoFullyAllocatedTypes(): void
    {
        RetributionType::factory()->create([
            'percentage' => 60,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
        RetributionType::factory()->create([
            'percentage' => 40,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
    }

    public function test_record_creates_balanced_journal_matching_active_type_split(): void
    {
        $this->cashAccount();
        $this->twoFullyAllocatedTypes();
        $branch = \App\Models\Branch::factory()->create();
        $user = User::factory()->create();

        $transaction = app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'umum',
            payerName: 'Ibu Sari',
            member: null,
            totalAmount: 100000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
        );

        $entry = $transaction->journalEntry;
        $this->assertEquals($entry->lines->sum('debit'), $entry->lines->sum('credit'));
        $this->assertCount(3, $entry->lines); // 2 kredit (jenis retribusi aktif) + 1 debit kas
        $this->assertEquals(100000, (float) $entry->lines->sum('debit'));
        $this->assertCount(2, $transaction->lines);
    }

    public function test_record_rejects_when_no_active_types_exist(): void
    {
        $this->cashAccount();
        $branch = \App\Models\Branch::factory()->create();
        $user = User::factory()->create();

        $this->expectException(RetributionException::class);

        app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'umum',
            payerName: 'Ibu Sari',
            member: null,
            totalAmount: 100000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
        );
    }

    public function test_record_rejects_when_active_percentages_do_not_sum_to_100(): void
    {
        $this->cashAccount();
        RetributionType::factory()->create([
            'percentage' => 50,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
        $branch = \App\Models\Branch::factory()->create();
        $user = User::factory()->create();

        $this->expectException(RetributionException::class);

        app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'umum',
            payerName: 'Ibu Sari',
            member: null,
            totalAmount: 100000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
        );
    }

    public function test_record_rejects_when_an_active_type_is_missing_coa_revenue_account(): void
    {
        $this->cashAccount();
        RetributionType::factory()->create([
            'percentage' => 100,
            'coa_revenue_account_id' => null,
            'is_active' => true,
        ]);
        $branch = \App\Models\Branch::factory()->create();
        $user = User::factory()->create();

        $this->expectException(RetributionException::class);

        app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'umum',
            payerName: 'Ibu Sari',
            member: null,
            totalAmount: 100000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
        );
    }

    public function test_record_snapshots_percentage_and_name_onto_lines_surviving_later_type_edits(): void
    {
        $this->cashAccount();
        $type = RetributionType::factory()->create([
            'name' => 'Retribusi Kebersihan',
            'percentage' => 100,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
        $branch = \App\Models\Branch::factory()->create();
        $user = User::factory()->create();

        $transaction = app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'umum',
            payerName: 'Ibu Sari',
            member: null,
            totalAmount: 100000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
        );

        $type->update(['name' => 'Retribusi Kebersihan (Baru)', 'percentage' => 50]);

        $line = $transaction->lines()->first();
        $this->assertEquals('Retribusi Kebersihan', $line->retribution_type_name);
        $this->assertEquals(100, (float) $line->percentage_applied);
    }

    public function test_umum_payer_stores_free_text_name_without_member_link(): void
    {
        $this->cashAccount();
        $this->twoFullyAllocatedTypes();
        $branch = \App\Models\Branch::factory()->create();
        $user = User::factory()->create();

        $transaction = app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'umum',
            payerName: 'Pembeli Lepas',
            member: null,
            totalAmount: 50000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
        );

        $this->assertEquals('Pembeli Lepas', $transaction->payer_name);
        $this->assertNull($transaction->member_id);
    }

    public function test_anggota_payer_snapshots_member_name_and_links_member_id(): void
    {
        $this->cashAccount();
        $this->twoFullyAllocatedTypes();
        $branch = \App\Models\Branch::factory()->create();
        $user = User::factory()->create();
        $member = Member::factory()->create(['name' => 'Kios Ibu Rina']);

        $transaction = app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'anggota',
            payerName: null,
            member: $member,
            totalAmount: 50000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
        );

        $this->assertEquals('Kios Ibu Rina', $transaction->payer_name);
        $this->assertEquals($member->id, $transaction->member_id);
    }
}
