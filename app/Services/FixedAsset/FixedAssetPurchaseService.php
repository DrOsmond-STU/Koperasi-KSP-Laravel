<?php

namespace App\Services\FixedAsset;

use App\Exceptions\FixedAsset\FixedAssetApprovalException;
use App\Models\ApprovalThreshold;
use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\User;
use App\Services\Accounting\JournalEngine;
use Illuminate\Support\Facades\DB;

/**
 * Pembelian Aktiva Tetap (PRD §14.2, Task 3.9). Below the ambang nilai
 * (`approval_thresholds` modul "aktiva_tetap") the asset posts (journal +
 * becomes "aktif") immediately at creation; above it, waits for approval
 * from someone other than the creator (AUTH-09/12) — same shape as
 * PurchaseService for Pembelian Barang.
 */
class FixedAssetPurchaseService
{
    private const DEFAULT_CASH_ACCOUNT_CODE = '1101';

    public function __construct(private readonly JournalEngine $journalEngine) {}

    /**
     * @param  array{
     *     fixed_asset_category_id: int, branch_id: int, code: string, name: string,
     *     description?: string|null, supplier_id?: int|null, ownership_document_number?: string|null,
     *     acquisition_date: string, acquisition_cost: string, residual_value: string,
     *     useful_life_months: int, depreciation_method: string, payment_method: string,
     * }  $data
     */
    public function submit(array $data, int $createdBy): FixedAsset
    {
        $needsApproval = bccomp((string) $data['acquisition_cost'], ApprovalThreshold::forModule('aktiva_tetap'), 2) > 0;

        return DB::transaction(function () use ($data, $createdBy, $needsApproval) {
            $asset = FixedAsset::query()->create([
                ...$data,
                'status' => 'menunggu_approval',
                'created_by' => $createdBy,
            ]);

            if (! $needsApproval) {
                $this->post($asset, $createdBy);
            }

            return $asset->fresh();
        });
    }

    public function approve(FixedAsset $asset, User $approver): FixedAsset
    {
        $this->guardCanDecide($asset, $approver);

        return DB::transaction(function () use ($asset, $approver) {
            $this->post($asset, $asset->created_by);
            $asset->update(['approved_by' => $approver->id]);

            return $asset->fresh();
        });
    }

    public function reject(FixedAsset $asset, User $approver): FixedAsset
    {
        $this->guardCanDecide($asset, $approver);

        $asset->update([
            'status' => 'ditolak',
            'approved_by' => $approver->id,
        ]);

        return $asset->fresh();
    }

    private function guardCanDecide(FixedAsset $asset, User $approver): void
    {
        if ($asset->status !== 'menunggu_approval') {
            throw FixedAssetApprovalException::notPending($asset->status);
        }

        if ($asset->created_by === $approver->id) {
            throw FixedAssetApprovalException::selfApproval();
        }
    }

    private function post(FixedAsset $asset, int $userId): void
    {
        $category = FixedAssetCategory::query()->findOrFail($asset->fixed_asset_category_id);

        $creditAccountId = $asset->isKredit()
            ? $asset->supplier->coa_payable_account_id
            : $this->cashAccount()->id;

        $entry = $this->journalEngine->post([
            'branch_id' => $asset->branch_id,
            'entry_date' => $asset->acquisition_date,
            'description' => "Pembelian aktiva tetap {$asset->name} ({$asset->code})",
            'created_by' => $userId,
            'source' => $asset,
            'lines' => [
                ['chart_of_account_id' => $category->coa_asset_account_id, 'debit' => $asset->acquisition_cost, 'credit' => 0],
                ['chart_of_account_id' => $creditAccountId, 'debit' => 0, 'credit' => $asset->acquisition_cost],
            ],
        ]);

        $asset->update([
            'status' => 'aktif',
            'journal_entry_id' => $entry->id,
        ]);
    }

    private function cashAccount(): ChartOfAccount
    {
        return ChartOfAccount::query()->where('code', self::DEFAULT_CASH_ACCOUNT_CODE)->firstOrFail();
    }
}
