<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\AuthorizesOwner;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    use Auditable, AuthorizesOwner, BelongsToBranch, HasFactory;

    protected $fillable = [
        'product_id',
        'branch_id',
        'stock_reason_id',
        'system_qty',
        'physical_qty',
        'variance_qty',
        'unit_cost',
        'amount',
        'status',
        'adjustment_date',
        'journal_entry_id',
        'created_by',
        'approved_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'reversal_journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'system_qty' => 'decimal:4',
            'physical_qty' => 'decimal:4',
            'variance_qty' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'amount' => 'decimal:2',
            'adjustment_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockReason(): BelongsTo
    {
        return $this->belongsTo(StockReason::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isSurplus(): bool
    {
        return bccomp((string) $this->variance_qty, '0', 4) > 0;
    }
}
