<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalanceStock extends Model
{
    protected $table = 'opening_balance_stock';

    protected $fillable = [
        'opening_balance_batch_id',
        'product_id',
        'qty',
        'unit_cost',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OpeningBalanceBatch::class, 'opening_balance_batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
