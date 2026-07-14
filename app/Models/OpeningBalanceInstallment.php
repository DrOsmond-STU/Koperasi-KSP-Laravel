<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalanceInstallment extends Model
{
    protected $table = 'opening_balance_installments';

    protected $fillable = [
        'opening_balance_batch_id',
        'opening_balance_loan_id',
        'installment_number',
        'due_date',
        'principal_amount',
        'interest_amount',
        'penalty_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OpeningBalanceBatch::class, 'opening_balance_batch_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(OpeningBalanceLoan::class, 'opening_balance_loan_id');
    }
}
