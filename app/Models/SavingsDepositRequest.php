<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Portal Anggota's "Setor Simpanan Mandiri" — a Xendit Invoice checkout in
 * progress. Paid automatically once the webhook confirms it
 * (SavingsDepositGatewayService::handleWebhookPaid()); never mutates
 * balance itself, only SavingsService::deposit() does.
 */
class SavingsDepositRequest extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'savings_account_id',
        'member_id',
        'amount',
        'status',
        'external_id',
        'xendit_invoice_id',
        'xendit_invoice_url',
        'requested_by',
        'paid_at',
        'savings_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'menunggu_pembayaran';
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

    public function savingsTransaction(): BelongsTo
    {
        return $this->belongsTo(SavingsTransaction::class);
    }
}
