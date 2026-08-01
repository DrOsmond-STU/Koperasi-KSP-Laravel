<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Portal Anggota's "Bayar Angsuran Mandiri" — a Xendit Invoice checkout in
 * progress. Paid automatically once the webhook confirms it
 * (LoanRepaymentGatewayService::handleWebhookPaid()); never touches
 * loan_schedules/journal_entries itself, only LoanRepaymentService does.
 */
class LoanRepaymentRequest extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'loan_id',
        'member_id',
        'amount',
        'status',
        'external_id',
        'xendit_invoice_id',
        'xendit_invoice_url',
        'requested_by',
        'paid_at',
        'loan_repayment_id',
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

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function loanRepayment(): BelongsTo
    {
        return $this->belongsTo(LoanRepayment::class);
    }
}
