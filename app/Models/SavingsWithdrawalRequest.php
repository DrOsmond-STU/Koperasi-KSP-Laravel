<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Portal Anggota's "Pengajuan Penarikan Simpanan" — a member's request,
 * decided by staff. Approving delegates to SavingsService::withdraw() (the
 * only writer of savings_transactions/savings_accounts.balance); this model
 * never mutates balance itself.
 */
class SavingsWithdrawalRequest extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'savings_account_id',
        'member_id',
        'amount',
        'status',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'decision_notes',
        'savings_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'menunggu';
    }

    public function savingsAccount(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function savingsTransaction(): BelongsTo
    {
        return $this->belongsTo(SavingsTransaction::class);
    }
}
