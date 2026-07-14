<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturn extends Model
{
    use Auditable, BelongsToBranch, HasFactory;

    protected $fillable = [
        'pos_sale_item_id',
        'branch_id',
        'qty',
        'unit_price',
        'unit_cost',
        'return_date',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:4',
            'return_date' => 'date',
        ];
    }

    public function posSaleItem(): BelongsTo
    {
        return $this->belongsTo(PosSaleItem::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
