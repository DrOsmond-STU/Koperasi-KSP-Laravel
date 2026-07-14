<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `balance` is a cached running total — only SavingsService may write it,
 * always inside the same DB transaction as the paired journal entry.
 */
class SavingsAccount extends Model
{
    use Auditable, BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'member_id',
        'savings_product_id',
        'account_number',
        'balance',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'opened_at' => 'date',
            'closed_at' => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function savingsProduct(): BelongsTo
    {
        return $this->belongsTo(SavingsProduct::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingsTransaction::class)->latest('id');
    }

    public function isActive(): bool
    {
        return $this->status === 'aktif';
    }
}
