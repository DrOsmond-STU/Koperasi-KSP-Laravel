<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalanceUpf extends Model
{
    protected $table = 'opening_balance_upf';

    protected $fillable = [
        'opening_balance_batch_id',
        'retribution_type_id',
        'amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OpeningBalanceBatch::class, 'opening_balance_batch_id');
    }

    public function retributionType(): BelongsTo
    {
        return $this->belongsTo(RetributionType::class);
    }
}
