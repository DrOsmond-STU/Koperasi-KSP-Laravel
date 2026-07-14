<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use Auditable, BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'member_id',
        'loan_product_id',
        'loan_number',
        'principal_amount',
        'tenor_months',
        'interest_rate_percentage',
        'provision_fee_amount',
        'required_approval_count',
        'status',
        'collectibility',
        'created_by',
        'submitted_at',
        'disbursed_at',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'interest_rate_percentage' => 'decimal:3',
            'provision_fee_amount' => 'decimal:2',
            'submitted_at' => 'date',
            'disbursed_at' => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(LoanApproval::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(LoanSchedule::class)->orderBy('installment_number');
    }

    public function approvalCount(): int
    {
        return $this->approvals()->where('decision', 'setuju')->count();
    }

    public function isFullyApproved(): bool
    {
        return $this->approvalCount() >= $this->required_approval_count;
    }

    public function hasBeenDecidedBy(int $userId): bool
    {
        return $this->approvals()->where('approved_by', $userId)->exists();
    }
}
