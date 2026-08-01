<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\AuthorizesOwner;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only — written only by SavingsService, always paired 1:1 with a
 * journal_entries row.
 */
class SavingsTransaction extends Model
{
    use Auditable, AuthorizesOwner, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'savings_account_id',
        'type',
        'amount',
        'balance_after',
        'journal_entry_id',
        'created_by',
        'description',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'reversal_journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function savingsAccount(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
