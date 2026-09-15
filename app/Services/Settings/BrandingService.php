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

    private const BACKGROUND_DIRECTORY = 'branding/login-background';

    /**
     * Mode ukuran gambar latar login, beserta labelnya di form pengaturan.
     * Satu-satunya daftar yang berlaku — dipakai form, validasi, dan
     * pemetaan ke CSS, supaya ketiganya tidak bisa saling menyimpang.
     *
     * @var array<string, string>
     */
    public const SIZE_MODES = [
        'cover' => 'Penuhi layar (sisi berlebih dipotong)',
        'contain' => 'Muat seluruhnya (tidak terpotong)',
        'stretch' => 'Regangkan mengikuti layar',
        'tile' => 'Ubin/diulang pada ukuran asli',
        'custom' => 'Skala kustom (persen)',
    ];

    public const DEFAULT_SIZE_MODE = 'cover';

    public const MIN_SCALE = 10;

    public const MAX_SCALE = 400;

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

    public function loginBackgroundUrl(): ?string
    {
        $path = $this->current()->login_background_path;

        return $path ? Storage::disk(self::LOGO_DISK)->url($path) : null;
    }

    /**
     * Gambar latar halaman login, sudah diterjemahkan ke nilai CSS siap
     * pakai. Pemetaan mode -> CSS sengaja di sini, bukan di Blade: kalau
     * nanti ada mode baru, hanya file ini yang berubah, dan view tetap
     * bodoh (tidak ada logika kondisional di template).
     *
     * Transparansi diterapkan sebagai "kerudung" (lapisan gradient warna
     * latar di atas gambar), bukan properti `opacity` pada <body> — opacity
     * pada elemen akan ikut memudarkan kartu login dan teksnya, sedangkan
     * yang diminta hanya gambarnya yang menipis.
     *
     * @return array{enabled: bool, url: string|null, css_size: string, css_repeat: string, veil_alpha: string}
     */
    public function loginBackground(): array
    {
        $setting = $this->current();
        $path = $setting->login_background_path;

        if (! $path) {
            return [
                'enabled' => false,
                'url' => null,
                'css_size' => 'cover',
                'css_repeat' => 'no-repeat',
                'veil_alpha' => '0.00',
            ];
        }

        $mode = $setting->login_background_size ?: self::DEFAULT_SIZE_MODE;
        $scale = (int) ($setting->login_background_scale ?? 100);
        $opacity = (int) ($setting->login_background_opacity ?? 100);

        [$cssSize, $cssRepeat] = match ($mode) {
            'contain' => ['contain', 'no-repeat'],
            'stretch' => ['100% 100%', 'no-repeat'],
            'tile' => ['auto', 'repeat'],
            'custom' => [$scale.'% auto', 'no-repeat'],
            default => ['cover', 'no-repeat'],
        };

        return [
            'enabled' => true,
            'url' => Storage::disk(self::LOGO_DISK)->url($path),
            'css_size' => $cssSize,
            'css_repeat' => $cssRepeat,
            // opacity 100 -> kerudung 0 (gambar utuh); opacity 0 -> kerudung
            // penuh (gambar tidak terlihat).
            'veil_alpha' => number_format((100 - $opacity) / 100, 2, '.', ''),
        ];
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
     * @param  array{app_name?: string, login_background_size?: string, login_background_scale?: int|string, login_background_opacity?: int|string, remove_login_background?: mixed}  $attributes
     */
    public function update(
        array $attributes,
        ?UploadedFile $logo,
        int $userId,
        ?UploadedFile $loginBackground = null,
    ): AppBrandingSetting {
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

        // Penghapusan diperiksa lebih dulu supaya "hapus + unggah baru" di
        // satu submit tetap berakhir dengan gambar yang baru, bukan kosong.
        if (filter_var($attributes['remove_login_background'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            if ($setting->login_background_path) {
                Storage::disk(self::LOGO_DISK)->delete($setting->login_background_path);
            }

            $data['login_background_path'] = null;
        }

        if ($loginBackground !== null) {
            if ($setting->login_background_path) {
                Storage::disk(self::LOGO_DISK)->delete($setting->login_background_path);
            }

            $data['login_background_path'] = $loginBackground->store(self::BACKGROUND_DIRECTORY, self::LOGO_DISK);
        }

        if (array_key_exists('login_background_size', $attributes)
            && array_key_exists((string) $attributes['login_background_size'], self::SIZE_MODES)) {
            $data['login_background_size'] = $attributes['login_background_size'];
        }

        if (array_key_exists('login_background_scale', $attributes) && $attributes['login_background_scale'] !== null) {
            $data['login_background_scale'] = max(
                self::MIN_SCALE,
                min(self::MAX_SCALE, (int) $attributes['login_background_scale']),
            );
        }

        if (array_key_exists('login_background_opacity', $attributes) && $attributes['login_background_opacity'] !== null) {
            $data['login_background_opacity'] = max(0, min(100, (int) $attributes['login_background_opacity']));
        }

        $setting->update($data);

        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::LOGO_DATA_URI_CACHE_KEY);

        return $setting->fresh();
    }
}
