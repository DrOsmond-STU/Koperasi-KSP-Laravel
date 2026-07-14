<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends Model
{
    use Auditable, BelongsToBranch, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'fixed_asset_category_id',
        'branch_id',
        'supplier_id',
        'ownership_document_number',
        'acquisition_date',
        'acquisition_cost',
        'residual_value',
        'useful_life_months',
        'depreciation_method',
        'depreciation_rate_percentage',
        'payment_method',
        'status',
        'journal_entry_id',
        'created_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'residual_value' => 'decimal:2',
            'depreciation_rate_percentage' => 'decimal:3',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FixedAssetCategory::class, 'fixed_asset_category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciationEntry::class);
    }

    public function isKredit(): bool
    {
        return $this->payment_method === 'kredit';
    }

    public function isActiveInService(): bool
    {
        return $this->status === 'aktif';
    }

    /**
     * Never cached on this row — always derived from
     * fixed_asset_depreciation_entries (Task 3.10).
     */
    public function accumulatedDepreciation(): string
    {
        return (string) ($this->depreciationEntries()->sum('depreciation_amount') ?: '0.00');
    }

    public function bookValue(): string
    {
        return bcsub((string) $this->acquisition_cost, $this->accumulatedDepreciation(), 2);
    }
}
