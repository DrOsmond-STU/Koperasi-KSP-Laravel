<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalanceAdjustmentLine extends Model
{
    protected $fillable = [
        'opening_balance_adjustment_id',
        'chart_of_account_id',
        'balance_before',
        'balance_after',
        'debit',
        'credit',
        'is_balancing',
    ];

    protected function casts(): array
    {
        return [
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'is_balancing' => 'boolean',
        ];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(OpeningBalanceAdjustment::class, 'opening_balance_adjustment_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }
}
