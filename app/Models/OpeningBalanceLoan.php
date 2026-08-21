<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpeningBalanceLoan extends Model
{
    protected $table = 'opening_balance_loans';

    protected $fillable = [
        'opening_balance_batch_id',
        'member_id',
        'loan_product_id',
        'external_loan_number',
        'disbursement_date',
        'original_principal',
        'outstanding_principal',
        'outstanding_interest',
        'tenor_days',
        'remaining_tenor_days',
        'next_installment_number',
        'next_due_date',
        'collectibility',
    ];

    protected function casts(): array
    {
        return [
            'disbursement_date' => 'date',
            'next_due_date' => 'date',
            'original_principal' => 'decimal:2',
            'outstanding_principal' => 'decimal:2',
            'outstanding_interest' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OpeningBalanceBatch::class, 'opening_balance_batch_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(OpeningBalanceInstallment::class)->orderBy('installment_number');
    }
}
