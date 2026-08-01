<?php

namespace App\Services\Settings;

use App\Models\AppBrandingSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Nama & logo aplikasi yang bisa diubah bebas oleh koperasi (Admin Sistem),
 * ditampilkan di halaman login & topbar (03_DESIGN.md §Topbar). Single
 * source of truth: `app_branding_settings` (selalu satu baris, id=1),
 * dibaca lewat cache agar tidak query DB di setiap request.
 */
class BrandingService
{
    private const CACHE_KEY = 'app_branding_settings';

    private const LOGO_DATA_URI_CACHE_KEY = 'app_branding_logo_data_uri';

    private const LOGO_DISK = 'public';

    private const LOGO_DIRECTORY = 'branding';

    public function current(): AppBrandingSetting
    {
        // Cache the plain attribute array, not the Eloquent instance itself:
        // unserializing a full model from a driver like Redis is fragile
        // (can surface as __PHP_Incomplete_Class) — a plain array round-trips
        // safely and we rebuild a proper "exists" model from it below.
        $attributes = Cache::rememberForever(self::CACHE_KEY, function () {
            return AppBrandingSetting::query()->firstOrCreate(
                ['id' => 1],
                ['app_name' => config('app.name'), 'logo_path' => null],
            )->getAttributes();
        });

        return (new AppBrandingSetting)->newInstance($attributes, exists: true);
    }

    public function appName(): string
    {
        return $this->current()->app_name;
    }

    public function logoUrl(): ?string
    {
        $path = $this->current()->logo_path;

        return $path ? Storage::disk(self::LOGO_DISK)->url($path) : null;
    }

    /**
     * Base64-embedded logo for PDF rendering (prints/layout.blade.php,
     * MemberCardRenderer). dompdf's `enable_remote` is off by default, so an
     * http(s) `logoUrl()` silently fails to render inside a PDF — embedding
     * the bytes directly sidesteps that, matching how GeneratesBarcodePng
     * already embeds barcodes.
     */
    public function logoDataUri(): ?string
    {
        $path = $this->current()->logo_path;

        if (! $path) {
            return null;
        }

        return Cache::rememberForever(self::LOGO_DATA_URI_CACHE_KEY, function () use ($path) {
            if (! Storage::disk(self::LOGO_DISK)->exists($path)) {
                return null;
            }

            $mime = Storage::disk(self::LOGO_DISK)->mimeType($path);
            $contents = Storage::disk(self::LOGO_DISK)->get($path);

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        });
    }

    /**
     * @param  array{app_name?: string}  $attributes
     */
    public function update(array $attributes, ?UploadedFile $logo, int $userId): AppBrandingSetting
    {
        // Writes always go through a model hydrated directly from the DB
        // (not the cache-reconstructed instance from current()) so Eloquent's
        // dirty-tracking/save() behaves normally.
        $setting = AppBrandingSetting::query()->firstOrCreate(
            ['id' => 1],
            ['app_name' => config('app.name'), 'logo_path' => null],
        );

        $data = ['updated_by' => $userId];

        if (array_key_exists('app_name', $attributes) && $attributes['app_name'] !== '') {
            $data['app_name'] = $attributes['app_name'];
        }

        if ($logo !== null) {
            if ($setting->logo_path) {
                Storage::disk(self::LOGO_DISK)->delete($setting->logo_path);
            }

            $data['logo_path'] = $logo->store(self::LOGO_DIRECTORY, self::LOGO_DISK);
        }

        $setting->update($data);

        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::LOGO_DATA_URI_CACHE_KEY);

        return $setting->fresh();
    }
}
