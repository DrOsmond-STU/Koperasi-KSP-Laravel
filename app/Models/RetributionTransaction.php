<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\AuthorizesOwner;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetributionTransaction extends Model
{
    use Auditable, AuthorizesOwner, BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'transaction_number',
        'transaction_date',
        'payer_type',
        'payer_name',
        'member_id',
        'retribution_type_id',
        'total_amount',
        'payment_method',
        'description',
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
            'transaction_date' => 'date',
            'total_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function retributionType(): BelongsTo
    {
        return $this->belongsTo(RetributionType::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RetributionTransactionLine::class);
    }

    public function isAnggota(): bool
    {
        return $this->payer_type === 'anggota';
    }
}
