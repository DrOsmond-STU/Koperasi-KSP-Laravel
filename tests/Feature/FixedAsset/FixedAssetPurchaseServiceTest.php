<?php

namespace Tests\Feature\FixedAsset;

use App\Exceptions\FixedAsset\FixedAssetApprovalException;
use App\Models\ApprovalThreshold;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FixedAssetCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Services\FixedAsset\FixedAssetPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md AUTH-09/AUTH-12 (approval berjenjang di atas ambang,
 * create != approve) and LED-01 (jurnal terbentuk, seimbang).
 */
class FixedAssetPurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private function baseData(FixedAssetCategory $category, Branch $branch): array
    {
        return [
            'fixed_asset_category_id' => $category->id,
            'branch_id' => $branch->id,
            'code' => 'AT-'.random_int(1000, 9999),
            'name' => 'Laptop Kantor',
            'acquisition_date' => now()->toDateString(),
            'acquisition_cost' => '12000000',
            'residual_value' => '1000000',
            'useful_life_months' => 36,
            'depreciation_method' => 'garis_lurus',
            'payment_method' => 'tunai',
        ];
    }

    public function test_purchase_below_threshold_posts_immediately(): void
    {
        ApprovalThreshold::query()->create(['module' => 'aktiva_tetap', 'threshold_amount' => 50000000]);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $branch = Branch::factory()->create();
        $category = FixedAssetCategory::factory()->create();
        $user = User::factory()->create();

        $asset = app(FixedAssetPurchaseService::class)->submit($this->baseData($category, $branch), $user->id);

        $this->assertEquals('aktif', $asset->status);
        $this->assertNotNull($asset->journal_entry_id);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $asset->journal_entry_id,
            'chart_of_account_id' => $category->coa_asset_account_id,
            'debit' => '12000000.00',
        ]);
    }

    public function test_purchase_above_threshold_waits_for_approval(): void
    {
        ApprovalThreshold::query()->create(['module' => 'aktiva_tetap', 'threshold_amount' => 1000000]);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $branch = Branch::factory()->create();
        $category = FixedAssetCategory::factory()->create();
        $user = User::factory()->create();

        $asset = app(FixedAssetPurchaseService::class)->submit($this->baseData($category, $branch), $user->id);

        $this->assertEquals('menunggu_approval', $asset->status);
        $this->assertNull($asset->journal_entry_id);
    }

    public function test_creator_cannot_approve_own_fixed_asset_purchase(): void
    {
        ApprovalThreshold::query()->create(['module' => 'aktiva_tetap', 'threshold_amount' => 1000000]);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $branch = Branch::factory()->create();
        $category = FixedAssetCategory::factory()->create();
        $user = User::factory()->create();

        $asset = app(FixedAssetPurchaseService::class)->submit($this->baseData($category, $branch), $user->id);

        $this->expectException(FixedAssetApprovalException::class);
        app(FixedAssetPurchaseService::class)->approve($asset, $user);
    }

    public function test_different_approver_posts_kredit_purchase_against_supplier_payable(): void
    {
        ApprovalThreshold::query()->create(['module' => 'aktiva_tetap', 'threshold_amount' => 1000000]);
        ChartOfAccount::factory()->create(['code' => '1101']);

        $branch = Branch::factory()->create();
        $category = FixedAssetCategory::factory()->create();
        $supplier = Supplier::factory()->create();
        $creator = User::factory()->create();
        $approver = User::factory()->create();

        $data = [
            ...$this->baseData($category, $branch),
            'payment_method' => 'kredit',
            'supplier_id' => $supplier->id,
        ];

        $asset = app(FixedAssetPurchaseService::class)->submit($data, $creator->id);
        $posted = app(FixedAssetPurchaseService::class)->approve($asset, $approver);

        $this->assertEquals('aktif', $posted->status);
        $this->assertEquals($approver->id, $posted->approved_by);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $posted->journal_entry_id,
            'chart_of_account_id' => $supplier->coa_payable_account_id,
            'credit' => '12000000.00',
        ]);
    }
}
