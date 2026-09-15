<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\AuthorizesOwner;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only — written only by SavingsService, always paired 1:1 with a
 * journal_entries row.
 */
class SavingsTransaction extends Model
{
    use Auditable, AuthorizesOwner, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'savings_account_id',
        'type',
        'transaction_date',
        'amount',
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
            'balance_after' => 'decimal:2',
            'transaction_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Tanggal transaksi untuk ditampilkan — transaction_date kalau ada,
     * fallback ke created_at untuk baris lama (sebelum kolom ini ada) yang
     * tidak punya tanggal asli lagi. Sama pola dengan LoanRepayment::paidOn().
     */
    public function transactionOn(): Carbon
    {
        return $this->transaction_date ?? $this->created_at;
    }

    public function savingsAccount(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class);
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
