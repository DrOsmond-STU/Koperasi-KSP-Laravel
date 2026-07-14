<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalThreshold extends Model
{
    protected $fillable = [
        'module',
        'threshold_amount',
    ];

    protected function casts(): array
    {
        return [
            'threshold_amount' => 'decimal:2',
        ];
    }

    /**
     * No configured row == threshold 0 (fail-safe: everything requires
     * approval until an admin explicitly configures a higher ambang).
     */
    public static function forModule(string $module): string
    {
        return (string) (static::query()->where('module', $module)->value('threshold_amount') ?? '0.00');
    }
}
