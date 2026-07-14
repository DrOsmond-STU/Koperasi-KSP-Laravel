<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanProductRateHistory extends Model
{
    use Auditable;

    protected $table = 'loan_product_rate_history';

    protected $fillable = [
        'loan_product_id',
        'rate_percentage',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'rate_percentage' => 'decimal:3',
            'effective_from' => 'date',
        ];
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }
}
