<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Singleton (always id=1) — read/write it through PrintSettingsService,
 * which owns the cache and the "always exactly one row" invariant. Mirrors
 * AppBrandingSetting's pattern exactly.
 */
class PrintSetting extends Model
{
    use Auditable;

    protected $fillable = [
        'id',
        'paper_size',
        'orientation',
        'margin_mm',
        'font_size_pt',
        'show_address',
        'address_text',
        'footer_text',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'show_address' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
