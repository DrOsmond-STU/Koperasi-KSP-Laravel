<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetributionTransactionLine extends Model
{
    protected $fillable = [
        'retribution_transaction_id',
        'retribution_type_id',
        'retribution_type_name',
        'percentage_applied',
        'chart_of_account_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'percentage_applied' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(RetributionTransaction::class, 'retribution_transaction_id');
    }

    public function retributionType(): BelongsTo
    {
        return $this->belongsTo(RetributionType::class);
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }
}
