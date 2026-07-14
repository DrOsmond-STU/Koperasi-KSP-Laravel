<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeType extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'unit_type',
        'tariff',
        'billing_period',
        'coa_receivable_account_id',
        'coa_revenue_account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tariff' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_receivable_account_id');
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_revenue_account_id');
    }

    public function isMeterBased(): bool
    {
        return in_array($this->unit_type, ['per_meter', 'per_m2'], true);
    }
}
