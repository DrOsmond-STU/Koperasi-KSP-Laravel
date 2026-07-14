<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'category',
        'unit',
        'purchase_price',
        'selling_price',
        'coa_inventory_account_id',
        'coa_cogs_account_id',
        'coa_sales_revenue_account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_inventory_account_id');
    }

    public function cogsAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_cogs_account_id');
    }

    public function salesRevenueAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_sales_revenue_account_id');
    }

    public function stockLedgerEntries(): HasMany
    {
        return $this->hasMany(StockLedgerEntry::class);
    }

    /**
     * Current running balance for this product at a branch, read from the
     * latest stock_ledger row — there is no denormalized stock column here.
     */
    public function currentStockAt(int $branchId): string
    {
        return (string) ($this->stockLedgerEntries()
            ->where('branch_id', $branchId)
            ->latest('id')
            ->value('running_qty') ?? '0.0000');
    }

    public function averageCostAt(int $branchId): string
    {
        return (string) ($this->stockLedgerEntries()
            ->where('branch_id', $branchId)
            ->latest('id')
            ->value('running_avg_cost') ?? '0.0000');
    }
}
