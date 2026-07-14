<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per rate change, keyed by effective_from — see
 * SavingsProduct::rateAt() for how "current rate on date X" is resolved.
 * Rows are never edited once created (a new rate = a new row).
 */
class SavingsProductRateHistory extends Model
{
    use Auditable;

    protected $table = 'savings_product_rate_history';

    protected $fillable = [
        'savings_product_id',
        'rate_percentage',
        'tiers',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'rate_percentage' => 'decimal:3',
            'tiers' => 'array',
            'effective_from' => 'date',
        ];
    }

    public function savingsProduct(): BelongsTo
    {
        return $this->belongsTo(SavingsProduct::class);
    }

    /**
     * Rate applicable to a given balance — uses `tiers` when present
     * (interest_method = tiered), otherwise the flat `rate_percentage`.
     */
    public function rateForBalance(float $balance): float
    {
        if (empty($this->tiers)) {
            return (float) $this->rate_percentage;
        }

        foreach ($this->tiers as $tier) {
            $min = (float) ($tier['min_balance'] ?? 0);
            $max = isset($tier['max_balance']) ? (float) $tier['max_balance'] : null;

            if ($balance >= $min && ($max === null || $balance <= $max)) {
                return (float) $tier['rate_percentage'];
            }
        }

        return (float) $this->rate_percentage;
    }
}
