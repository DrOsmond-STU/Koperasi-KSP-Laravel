<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment EVENT against a loan — written only by LoanRepaymentService.
 * May cover several loan_schedules rows in one lump sum; principal_portion/
 * interest_portion are the totals across every row it touched, matching the
 * two credit lines on its journal entry.
 */
class LoanRepayment extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'loan_id',
        'amount',
        'principal_portion',
        'interest_portion',
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
            'principal_portion' => 'decimal:2',
            'interest_portion' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
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
