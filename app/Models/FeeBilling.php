<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeBilling extends Model
{
    protected $fillable = [
        'tenant_id',
        'fee_type_id',
        'period',
        'meter_start',
        'meter_end',
        'amount',
        'paid_amount',
        'status',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'meter_start' => 'decimal:2',
            'meter_end' => 'decimal:2',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class);
    }

    public function remainingAmount(): float
    {
        return round((float) $this->amount - (float) $this->paid_amount, 2);
    }
}
