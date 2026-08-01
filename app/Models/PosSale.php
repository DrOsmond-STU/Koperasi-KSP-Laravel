<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSale extends Model
{
    use Auditable, BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'savings_account_id',
        'loan_id',
        'sale_number',
        'sale_date',
        'payment_method',
        'total_amount',
        'total_cogs',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'total_amount' => 'decimal:2',
            'total_cogs' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class);
    }

    public function savingsAccount(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function isPotongSimpanan(): bool
    {
        return $this->payment_method === 'potong_simpanan';
    }
}
