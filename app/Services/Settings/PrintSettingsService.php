<?php

namespace App\Services\Settings;

use App\Models\PrintSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Shared letterhead/paper layout for every cetakan (prints/layout.blade.php)
 * — single source of truth, always one row, read through a forever-cache.
 * Mirrors BrandingService exactly.
 */
class PrintSettingsService
{
    private const CACHE_KEY = 'print_settings';

    public function current(): PrintSetting
    {
        // Defaults must be spelled out here (not left to the migration's
        // column defaults) — Eloquent's create() never reads DB-applied
        // defaults back into the in-memory instance, so an implicit
        // firstOrCreate(['id' => 1]) would cache null for every column on
        // first access and crash Dompdf::setPaper() with a null orientation.
        $attributes = Cache::rememberForever(self::CACHE_KEY, function () {
            return PrintSetting::query()->firstOrCreate(
                ['id' => 1],
                ['paper_size' => 'a4', 'orientation' => 'portrait', 'margin_mm' => 15, 'font_size_pt' => 11, 'show_address' => true],
            )->getAttributes();
        });

        return (new PrintSetting)->newInstance($attributes, exists: true);
    }

    /**
     * @param  array{paper_size?: string, orientation?: string, margin_mm?: int, font_size_pt?: int, show_address?: bool, address_text?: ?string, footer_text?: ?string}  $attributes
     */
    public function update(array $attributes, int $userId): PrintSetting
    {
        $setting = PrintSetting::query()->firstOrCreate(['id' => 1]);

        $setting->update([...$attributes, 'updated_by' => $userId]);

        Cache::forget(self::CACHE_KEY);

        return $setting->fresh();
    }
}
