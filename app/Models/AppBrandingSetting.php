<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Singleton (always id=1) — read/write it through BrandingService, which
 * owns the cache and the "always exactly one row" invariant.
 */
class AppBrandingSetting extends Model
{
    use Auditable;

    protected $fillable = [
        // 'id' is intentionally fillable: BrandingService relies on
        // firstOrCreate(['id' => 1], ...) to enforce the singleton row —
        // without 'id' in $fillable, mass assignment silently drops it and
        // a new auto-incremented row is created on every call.
        'id',
        'app_name',
        'logo_path',
        'updated_by',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
