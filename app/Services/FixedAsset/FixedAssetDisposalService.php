<?php

namespace App\Services\FixedAsset;

use App\Exceptions\FixedAsset\FixedAssetDisposalException;
use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\FixedAssetDisposal;
use App\Services\Accounting\JournalEngine;
use Illuminate\Support\Facades\DB;

/**
 * Pelepasan Aktiva Tetap (PRD §14.2, Task 3.11, DEP-05). Removes Nilai
 * Perolehan & Akumulasi Penyusutan from the balance sheet in one journal
 * entry and books the sale-vs-book-value difference as Laba/Rugi
 * Pelepasan. No approval workflow — PRD only requires create/approve
 * segregation for the acquisition, not disposal.
 */
class FixedAssetDisposalService
{
    private const GAIN_ACCOUNT_CODE = '4903'; // Laba Pelepasan Aset Tetap

    private const LOSS_ACCOUNT_CODE = '5904'; // Rugi Pelepasan Aset Tetap

    private const DEFAULT_CASH_ACCOUNT_CODE = '1101';

    public function __construct(private readonly JournalEngine $journalEngine) {}

    public function dispose(
        FixedAsset $asset,
        string $disposalType,
        string $saleAmount,
        int $createdBy,
        ?\DateTimeInterface $date = null,
    ): FixedAssetDisposal {
        if (! $asset->isActiveInService()) {
            throw FixedAssetDisposalException::notActive($asset->status);
        }

        $saleAmount = $disposalType === 'dijual' ? $saleAmount : '0.00';

        return DB::transaction(function () use ($asset, $disposalType, $saleAmount, $createdBy, $date) {
            $category = $asset->category;
            $accumulatedDepreciation = $asset->accumulatedDepreciation();
            $bookValue = $asset->bookValue();
            $gainLoss = bcsub($saleAmount, $bookValue, 2);

            $lines = [
                ['chart_of_account_id' => $category->coa_asset_account_id, 'debit' => 0, 'credit' => $asset->acquisition_cost],
            ];

            if (bccomp($accumulatedDepreciation, '0', 2) > 0) {
                $lines[] = ['chart_of_account_id' => $category->coa_accumulated_depreciation_account_id, 'debit' => $accumulatedDepreciation, 'credit' => 0];
            }

            if (bccomp($saleAmount, '0', 2) > 0) {
                $lines[] = ['chart_of_account_id' => $this->cashAccount()->id, 'debit' => $saleAmount, 'credit' => 0];
            }

            if (bccomp($gainLoss, '0', 2) > 0) {
                $lines[] = ['chart_of_account_id' => $this->gainAccount()->id, 'debit' => 0, 'credit' => $gainLoss];
            } elseif (bccomp($gainLoss, '0', 2) < 0) {
                $lines[] = ['chart_of_account_id' => $this->lossAccount()->id, 'debit' => bcmul($gainLoss, '-1', 2), 'credit' => 0];
            }

            $entry = $this->journalEngine->post([
                'branch_id' => $asset->branch_id,
                'entry_date' => ($date ?? now())->format('Y-m-d'),
                'description' => "Pelepasan aktiva tetap {$asset->name} ({$asset->code}) — ".ucfirst($disposalType),
                'created_by' => $createdBy,
                'source' => $asset,
                'lines' => $lines,
            ]);

            $disposal = FixedAssetDisposal::query()->create([
                'fixed_asset_id' => $asset->id,
                'disposal_type' => $disposalType,
                'sale_amount' => $saleAmount,
                'book_value_at_disposal' => $bookValue,
                'gain_loss_amount' => $gainLoss,
                'disposal_date' => ($date ?? now())->format('Y-m-d'),
                'journal_entry_id' => $entry->id,
                'created_by' => $createdBy,
            ]);

            // "Hilang" has no distinct lifecycle status in PRD §14.1 — it is
            // written off the books the same as "Dihapusbukukan".
            $asset->update([
                'status' => $disposalType === 'dijual' ? 'dijual' : 'dihapusbukukan',
            ]);

            return $disposal;
        });
    }

    /**
     * Pembatalan pelepasan — memulihkan `fixed_assets.status` ke `aktif`
     * dan membalik jurnal (JournalEngine::reverse() otomatis membalik SEMUA
     * baris — nilai perolehan, akumulasi penyusutan, kas, laba/rugi —
     * sekaligus, tidak ada ledger terpisah seperti stock_ledger yang perlu
     * dibalik manual di sini).
     */
    public function reverseDisposal(FixedAssetDisposal $disposal, string $reason, int $cancelledBy): FixedAssetDisposal
    {
        if ($disposal->isCancelled()) {
            throw FixedAssetDisposalException::alreadyCancelled();
        }

        return DB::transaction(function () use ($disposal, $reason, $cancelledBy) {
            $reversalEntry = $this->journalEngine->reverse($disposal->journalEntry, $reason, $cancelledBy);

            $disposal->fixedAsset->update(['status' => 'aktif']);

            $disposal->update([
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledBy,
                'cancellation_reason' => $reason,
                'reversal_journal_entry_id' => $reversalEntry->id,
            ]);

            return $disposal->fresh();
        });
    }

    private function cashAccount(): ChartOfAccount
    {
        return ChartOfAccount::query()->where('code', self::DEFAULT_CASH_ACCOUNT_CODE)->firstOrFail();
    }

    private function gainAccount(): ChartOfAccount
    {
        return ChartOfAccount::query()->where('code', self::GAIN_ACCOUNT_CODE)->firstOrFail();
    }

    private function lossAccount(): ChartOfAccount
    {
        return ChartOfAccount::query()->where('code', self::LOSS_ACCOUNT_CODE)->firstOrFail();
    }
}
