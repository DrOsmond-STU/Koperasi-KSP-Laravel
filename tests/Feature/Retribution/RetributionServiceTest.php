<?php

namespace Tests\Feature\Retribution;

use App\Exceptions\Retribution\RetributionException;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Member;
use App\Models\RetributionType;
use App\Models\User;
use App\Services\Retribution\RetributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * record() punya dua mode (lihat docblock RetributionService): ANGGOTA
 * (split otomatis ke seluruh jenis "split", percentage < 100, harus total
 * 100%) dan UMUM (satu jenis dipilih eksplisit via parameter
 * $retributionType, harus percentage = 100% & aktif).
 */
class RetributionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function cashAccount(): ChartOfAccount
    {
        return ChartOfAccount::factory()->create(['code' => '1101600']);
    }

    /**
     * @return array{0: RetributionType, 1: RetributionType}
     */
    private function twoSplitTypes(): array
    {
        $a = RetributionType::factory()->create([
            'percentage' => 60,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
        $b = RetributionType::factory()->create([
            'percentage' => 40,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);

        return [$a, $b];
    }

    private function umumType(): RetributionType
    {
        return RetributionType::factory()->create([
            'percentage' => 100,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
    }

    // ================= ANGGOTA (split otomatis) =================

    public function test_anggota_record_creates_balanced_journal_matching_split_types(): void
    {
        $this->cashAccount();
        $this->twoSplitTypes();
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $member = Member::factory()->create();

        $transaction = app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'anggota',
            payerName: null,
            member: $member,
            totalAmount: 100000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
        );

        $entry = $transaction->journalEntry;
        $this->assertEquals($entry->lines->sum('debit'), $entry->lines->sum('credit'));
        $this->assertCount(3, $entry->lines); // 2 kredit (jenis split) + 1 debit kas
        $this->assertEquals(100000, (float) $entry->lines->sum('debit'));
        $this->assertCount(2, $transaction->lines);
        $this->assertNull($transaction->retribution_type_id);
    }

    public function test_anggota_record_rejects_when_no_active_split_types_exist(): void
    {
        $this->cashAccount();
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $member = Member::factory()->create();

        $this->expectException(RetributionException::class);

        app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'anggota',
            payerName: null,
            member: $member,
            totalAmount: 100000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
        );
    }

    public function test_anggota_record_rejects_when_split_percentages_do_not_sum_to_100(): void
    {
        $this->cashAccount();
        RetributionType::factory()->create([
            'percentage' => 50,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $member = Member::factory()->create();

        $this->expectException(RetributionException::class);

        app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'anggota',
            payerName: null,
            member: $member,
            totalAmount: 100000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
        );
    }

    public function test_anggota_record_rejects_when_a_split_type_is_missing_coa_revenue_account(): void
    {
        $this->cashAccount();
        RetributionType::factory()->create([
            'percentage' => 60,
            'coa_revenue_account_id' => null,
            'is_active' => true,
        ]);
        RetributionType::factory()->create([
            'percentage' => 40,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $member = Member::factory()->create();

        $this->expectException(RetributionException::class);

        app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'anggota',
            payerName: null,
            member: $member,
            totalAmount: 100000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
        );
    }

    public function test_anggota_record_snapshots_percentage_and_name_onto_lines_surviving_later_type_edits(): void
    {
        $this->cashAccount();
        [$type] = $this->twoSplitTypes();
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $member = Member::factory()->create();

        $transaction = app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'anggota',
            payerName: null,
            member: $member,
            totalAmount: 100000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
        );

        $type->update(['name' => 'Retribusi Kebersihan (Baru)', 'percentage' => 30]);

        $line = $transaction->lines()->where('retribution_type_id', $type->id)->firstOrFail();
        $this->assertNotEquals('Retribusi Kebersihan (Baru)', $line->retribution_type_name);
        $this->assertEquals(60, (float) $line->percentage_applied);
    }

    public function test_anggota_payer_snapshots_member_name_and_links_member_id(): void
    {
        $this->cashAccount();
        $this->twoSplitTypes();
        $branch = Branch::factory()->create();
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
        $this->assertNull($transaction->retribution_type_id);
    }

    // ================= UMUM (satu jenis dipilih eksplisit) =================

    public function test_umum_record_credits_single_selected_type_fully(): void
    {
        $this->cashAccount();
        $type = $this->umumType();
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        $transaction = app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'umum',
            payerName: 'Ibu Sari',
            member: null,
            totalAmount: 100000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
            retributionType: $type,
        );

        $entry = $transaction->journalEntry;
        $this->assertEquals($entry->lines->sum('debit'), $entry->lines->sum('credit'));
        $this->assertCount(2, $entry->lines); // 1 kredit + 1 debit kas
        $this->assertEquals(100000, (float) $entry->lines->sum('debit'));
        $this->assertCount(1, $transaction->lines);
        $this->assertEquals($type->id, $transaction->retribution_type_id);
    }

    public function test_umum_record_rejects_when_no_type_selected(): void
    {
        $this->cashAccount();
        $this->umumType();
        $branch = Branch::factory()->create();
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

    public function test_umum_record_rejects_type_that_is_not_100_percent(): void
    {
        $this->cashAccount();
        $type = RetributionType::factory()->create([
            'percentage' => 60,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
        $branch = Branch::factory()->create();
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
            retributionType: $type,
        );
    }

    public function test_umum_record_rejects_inactive_type(): void
    {
        $this->cashAccount();
        $type = RetributionType::factory()->create([
            'percentage' => 100,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => false,
        ]);
        $branch = Branch::factory()->create();
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
            retributionType: $type,
        );
    }

    public function test_umum_record_rejects_type_missing_coa_revenue_account(): void
    {
        $this->cashAccount();
        $type = RetributionType::factory()->create([
            'percentage' => 100,
            'coa_revenue_account_id' => null,
            'is_active' => true,
        ]);
        $branch = Branch::factory()->create();
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
            retributionType: $type,
        );
    }

    public function test_umum_payer_stores_free_text_name_without_member_link(): void
    {
        $this->cashAccount();
        $type = $this->umumType();
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        $transaction = app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'umum',
            payerName: 'Pembeli Lepas',
            member: null,
            totalAmount: 50000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
            retributionType: $type,
        );

        $this->assertEquals('Pembeli Lepas', $transaction->payer_name);
        $this->assertNull($transaction->member_id);
    }
}
