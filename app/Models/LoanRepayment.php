<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment EVENT against a loan — written only by LoanRepaymentService.
 * May cover several loan_schedules rows in one lump sum; principal_portion/
 * interest_portion are the totals across every row it touched, matching the
 * two credit lines on its journal entry.
 */
class LoanRepayment extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'loan_id',
        'amount',
        'principal_portion',
        'interest_portion',
        'balance_after',
        'paid_at',
        'migrated_at',
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
            'principal_portion' => 'decimal:2',
            'interest_portion' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'paid_at' => 'date',
            'migrated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Dibawa dari sistem lama lewat import riwayat, bukan transaksi yang
     * terjadi di aplikasi ini — tidak berjurnal dan tidak mengubah saldo.
     */
    public function isMigrated(): bool
    {
        return $this->migrated_at !== null;
    }

    /** Tanggal pembayaran sebenarnya; baris lama jatuh kembali ke created_at. */
    public function paidOn(): \Illuminate\Support\Carbon
    {
        return $this->paid_at ?? $this->created_at;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
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
