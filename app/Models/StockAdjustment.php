<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    use Auditable, BelongsToBranch, HasFactory;

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
        ];
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

    public function isSurplus(): bool
    {
        return bccomp((string) $this->variance_qty, '0', 4) > 0;
    }
}
