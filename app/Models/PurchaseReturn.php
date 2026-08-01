<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturn extends Model
{
    use Auditable, BelongsToBranch, HasFactory;

    protected $fillable = [
        'purchase_item_id',
        'branch_id',
        'stock_reason_id',
        'qty',
        'unit_cost',
        'amount',
        'return_date',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'amount' => 'decimal:2',
            'return_date' => 'date',
        ];
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
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
}
