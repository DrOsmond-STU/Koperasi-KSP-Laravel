<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only, written only by DepreciationEngine.
 */
class FixedAssetDepreciationEntry extends Model
{
    protected $fillable = [
        'fixed_asset_id',
        'period',
        'opening_book_value',
        'depreciation_amount',
        'closing_book_value',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_book_value' => 'decimal:2',
            'depreciation_amount' => 'decimal:2',
            'closing_book_value' => 'decimal:2',
        ];
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
