<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use Auditable, HasFactory;

    /**
     * Codes hardcoded by literal string across ~10 core services
     * (SavingsService, PurchaseService, StockAdjustmentService,
     * FixedAssetDisposalService, RetributionService, ...) via
     * `where('code', '1101')`-style lookups rather than a foreign key —
     * renaming or deleting these breaks cash/adjustment/disposal posting
     * app-wide with nothing at the DB layer to catch it. The admin CRUD
     * screen locks `code` edits and blocks deletion for any account here.
     */
    public const PROTECTED_CODES = ['1101', '1132', '4903', '4904', '5212', '5904'];

    protected $fillable = [
        'code',
        'name',
        'type',
        'group',
        'normal_balance',
        'is_postable',
        'parent_code',
        'statement',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_postable' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_code', 'code');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_code', 'code');
    }

    public function isProtected(): bool
    {
        return in_array($this->code, self::PROTECTED_CODES, true);
    }
}
