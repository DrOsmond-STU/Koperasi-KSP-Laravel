<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetDisposal extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'fixed_asset_id',
        'disposal_type',
        'sale_amount',
        'book_value_at_disposal',
        'gain_loss_amount',
        'disposal_date',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sale_amount' => 'decimal:2',
            'book_value_at_disposal' => 'decimal:2',
            'gain_loss_amount' => 'decimal:2',
            'disposal_date' => 'date',
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

    public function isGain(): bool
    {
        return bccomp((string) $this->gain_loss_amount, '0', 2) > 0;
    }
}
