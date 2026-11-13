<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu kali koreksi saldo awal Neraca pada batch migrasi yang sudah dikunci
 * (lihat OpeningBalanceAdjustmentService).
 */
class OpeningBalanceAdjustment extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'opening_balance_batch_id',
        'branch_id',
        'journal_entry_id',
        'entry_date',
        'reason',
        'total_debit',
        'total_credit',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OpeningBalanceBatch::class, 'opening_balance_batch_id');
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
        return $this->hasMany(OpeningBalanceAdjustmentLine::class);
    }
}
