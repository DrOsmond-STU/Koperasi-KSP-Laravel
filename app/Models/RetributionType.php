<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetributionType extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'percentage',
        'coa_revenue_account_id',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_revenue_account_id');
    }

    public function transactionLines(): HasMany
    {
        return $this->hasMany(RetributionTransactionLine::class);
    }
}
