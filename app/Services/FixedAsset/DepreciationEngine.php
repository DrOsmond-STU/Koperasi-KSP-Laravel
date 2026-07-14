<?php

namespace App\Services\FixedAsset;

use App\Models\DepreciationBatchRun;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciationEntry;
use App\Services\Accounting\JournalEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Batch penyusutan bulanan (PRD §14.2, Task 3.10). Runs over every asset
 * with `status = aktif`; idempotent per (fixed_asset_id, period) — DEP-02 —
 * and stops automatically once book value reaches residual value — DEP-03.
 * Formulas per DEP-04:
 *   Garis Lurus:    (Nilai Perolehan − Nilai Residu) / Umur Ekonomis (bulan)
 *   Saldo Menurun:  Nilai Buku Berjalan × Tarif
 */
class DepreciationEngine
{
    private const SCALE = 2;

    public function __construct(private readonly JournalEngine $journalEngine) {}

    /**
     * @return array{created: int, skipped: int}
     */
    public function run(string $period, int $triggeredBy): array
    {
        $created = 0;
        $skipped = 0;

        FixedAsset::query()->where('status', 'aktif')->each(function (FixedAsset $asset) use ($period, $triggeredBy, &$created, &$skipped) {
            $entry = $this->processAsset($asset, $period, $triggeredBy);
            $entry !== null ? $created++ : $skipped++;
        });

        // AUD-06: run id, periode, jumlah aset diproses.
        DepreciationBatchRun::query()->create([
            'period' => $period,
            'assets_processed' => $created,
            'assets_skipped' => $skipped,
            'triggered_by' => $triggeredBy,
        ]);

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Returns null when skipped (already posted for this period — DEP-02 —
     * or book value already at residual value — DEP-03).
     */
    public function processAsset(FixedAsset $asset, string $period, int $triggeredBy): ?FixedAssetDepreciationEntry
    {
        if (FixedAssetDepreciationEntry::query()->where('fixed_asset_id', $asset->id)->where('period', $period)->exists()) {
            return null;
        }

        $openingBookValue = $asset->bookValue();
        $maxDepreciable = bcsub($openingBookValue, (string) $asset->residual_value, self::SCALE);

        if (bccomp($maxDepreciable, '0', self::SCALE) <= 0) {
            return null;
        }

        $rawAmount = $asset->depreciation_method === 'garis_lurus'
            ? bcdiv(bcsub((string) $asset->acquisition_cost, (string) $asset->residual_value, self::SCALE), (string) $asset->useful_life_months, self::SCALE)
            : bcmul($openingBookValue, bcdiv((string) $asset->depreciation_rate_percentage, '100', 6), self::SCALE);

        // Never depreciate past the residual value, even on the final month.
        $amount = bccomp($rawAmount, $maxDepreciable, self::SCALE) > 0 ? $maxDepreciable : $rawAmount;

        if (bccomp($amount, '0', self::SCALE) <= 0) {
            return null;
        }

        return DB::transaction(function () use ($asset, $period, $openingBookValue, $amount, $triggeredBy) {
            $category = $asset->category;
            $entryDate = Carbon::createFromFormat('Y-m', $period)->endOfMonth()->toDateString();

            $journalEntry = $this->journalEngine->post([
                'branch_id' => $asset->branch_id,
                'entry_date' => $entryDate,
                'description' => "Penyusutan {$asset->name} ({$asset->code}) periode {$period}",
                'created_by' => $triggeredBy,
                'source' => $asset,
                'idempotency_key' => "penyusutan-{$asset->id}-{$period}",
                'lines' => [
                    ['chart_of_account_id' => $category->coa_depreciation_expense_account_id, 'debit' => $amount, 'credit' => 0],
                    ['chart_of_account_id' => $category->coa_accumulated_depreciation_account_id, 'debit' => 0, 'credit' => $amount],
                ],
            ]);

            return FixedAssetDepreciationEntry::query()->create([
                'fixed_asset_id' => $asset->id,
                'period' => $period,
                'opening_book_value' => $openingBookValue,
                'depreciation_amount' => $amount,
                'closing_book_value' => bcsub($openingBookValue, $amount, self::SCALE),
                'journal_entry_id' => $journalEntry->id,
                'created_by' => $triggeredBy,
            ]);
        });
    }
}
