<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpeningBalanceBatch extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'cutoff_date',
        'status',
        'locked_by',
        'locked_at',
        'reconciliation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_date' => 'date',
            'locked_at' => 'datetime',
            'reconciliation_snapshot' => 'array',
        ];
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function savings(): HasMany
    {
        return $this->hasMany(OpeningBalanceSaving::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(OpeningBalanceLoan::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(OpeningBalanceInstallment::class);
    }

    public function coaLines(): HasMany
    {
        return $this->hasMany(OpeningBalanceCoa::class);
    }

    public function upf(): HasMany
    {
        return $this->hasMany(OpeningBalanceUpf::class);
    }

    public function stock(): HasMany
    {
        return $this->hasMany(OpeningBalanceStock::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
