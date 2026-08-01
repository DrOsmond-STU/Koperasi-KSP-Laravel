<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\AuthorizesOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetDisposal extends Model
{
    use Auditable, AuthorizesOwner, HasFactory;

    protected $fillable = [
        'fixed_asset_id',
        'disposal_type',
        'sale_amount',
        'book_value_at_disposal',
        'gain_loss_amount',
        'disposal_date',
        'journal_entry_id',
        'created_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'reversal_journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'sale_amount' => 'decimal:2',
            'book_value_at_disposal' => 'decimal:2',
            'gain_loss_amount' => 'decimal:2',
            'disposal_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isGain(): bool
    {
        return bccomp((string) $this->gain_loss_amount, '0', 2) > 0;
    }
}
