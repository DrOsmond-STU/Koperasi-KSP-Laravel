<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Services\Settings\BrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BrandingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The "array" cache driver (testing env) keeps its storage for the
        // whole PHPUnit process, not just one test — flush explicitly so
        // BrandingService::current() doesn't see a value cached by a
        // previous test.
        Cache::flush();
    }

    public function test_default_branding_falls_back_to_config_app_name(): void
    {
        $branding = app(BrandingService::class);

        $this->assertEquals(config('app.name'), $branding->appName());
        $this->assertNull($branding->logoUrl());
    }

    public function test_updating_name_and_logo_persists_and_invalidates_cache(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $branding = app(BrandingService::class);

        // warm the cache with the default value first
        $branding->current();

        $logo = UploadedFile::fake()->image('logo.png', 200, 200);
        $branding->update(['app_name' => 'Koperasi Maju Bersama'], $logo, $user->id);

        $this->assertEquals('Koperasi Maju Bersama', $branding->appName());
        $this->assertNotNull($branding->logoUrl());

        $setting = $branding->current();
        Storage::disk('public')->assertExists($setting->logo_path);
        $this->assertEquals($user->id, $setting->updated_by);
    }

    public function test_login_background_is_disabled_until_one_is_uploaded(): void
    {
        $background = app(BrandingService::class)->loginBackground();

        $this->assertFalse($background['enabled']);
        $this->assertNull($background['url']);
        $this->assertNull(app(BrandingService::class)->loginBackgroundUrl());
    }

    public function test_uploading_login_background_stores_the_file_and_enables_it(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $branding = app(BrandingService::class);

        // warm the cache with the default value first
        $branding->current();

        $image = UploadedFile::fake()->image('latar.jpg', 1920, 1080);
        $branding->update([], null, $user->id, $image);

        $setting = $branding->current();
        Storage::disk('public')->assertExists($setting->login_background_path);

        $background = $branding->loginBackground();
        $this->assertTrue($background['enabled']);
        $this->assertNotNull($background['url']);
        $this->assertEquals($user->id, $setting->updated_by);
    }

    public function test_replacing_login_background_deletes_the_previous_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $branding = app(BrandingService::class);

        $branding->update([], null, $user->id, UploadedFile::fake()->image('satu.jpg'));
        $firstPath = $branding->current()->login_background_path;

        $branding->update([], null, $user->id, UploadedFile::fake()->image('dua.jpg'));

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($branding->current()->login_background_path);
    }

    public function test_removing_login_background_clears_the_path_and_the_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $branding = app(BrandingService::class);

        $branding->update([], null, $user->id, UploadedFile::fake()->image('latar.jpg'));
        $path = $branding->current()->login_background_path;

        $branding->update(['remove_login_background' => '1'], null, $user->id);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($branding->current()->login_background_path);
        $this->assertFalse($branding->loginBackground()['enabled']);
    }

    /**
     * Mengunggah gambar baru sekaligus mencentang "hapus" harus berakhir
     * dengan gambar yang baru — bukan kosong.
     */
    public function test_uploading_while_remove_is_checked_keeps_the_new_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $branding = app(BrandingService::class);

        $branding->update([], null, $user->id, UploadedFile::fake()->image('lama.jpg'));

        $branding->update(
            ['remove_login_background' => '1'],
            null,
            $user->id,
            UploadedFile::fake()->image('baru.jpg'),
        );

        $this->assertNotNull($branding->current()->login_background_path);
        $this->assertTrue($branding->loginBackground()['enabled']);
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: string, 3: string}>
     */
    public static function sizeModeProvider(): array
    {
        return [
            'cover' => ['cover', 100, 'cover', 'no-repeat'],
            'contain' => ['contain', 100, 'contain', 'no-repeat'],
            'stretch' => ['stretch', 100, '100% 100%', 'no-repeat'],
            'tile' => ['tile', 100, 'auto', 'repeat'],
            'custom' => ['custom', 60, '60% auto', 'no-repeat'],
        ];
    }

    #[DataProvider('sizeModeProvider')]
    public function test_size_mode_maps_to_css(string $mode, int $scale, string $expectedSize, string $expectedRepeat): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $branding = app(BrandingService::class);

        $branding->update(
            ['login_background_size' => $mode, 'login_background_scale' => $scale],
            null,
            $user->id,
            UploadedFile::fake()->image('latar.jpg'),
        );

        $background = $branding->loginBackground();

        $this->assertSame($expectedSize, $background['css_size']);
        $this->assertSame($expectedRepeat, $background['css_repeat']);
    }

    public function test_opacity_is_translated_into_an_inverted_veil_alpha(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $branding = app(BrandingService::class);

        $branding->update(
            ['login_background_opacity' => 40],
            null,
            $user->id,
            UploadedFile::fake()->image('latar.jpg'),
        );

        // 40% gambar terlihat berarti 60% kerudung warna latar di atasnya.
        $this->assertSame('0.60', $branding->loginBackground()['veil_alpha']);

        $branding->update(['login_background_opacity' => 100], null, $user->id);
        $this->assertSame('0.00', $branding->loginBackground()['veil_alpha']);
    }

    public function test_scale_and_opacity_are_clamped_to_their_allowed_range(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $branding = app(BrandingService::class);

        $branding->update(
            ['login_background_scale' => 5000, 'login_background_opacity' => 250],
            null,
            $user->id,
            UploadedFile::fake()->image('latar.jpg'),
        );

        $setting = $branding->current();
        $this->assertSame(BrandingService::MAX_SCALE, (int) $setting->login_background_scale);
        $this->assertSame(100, (int) $setting->login_background_opacity);
    }

    public function test_unknown_size_mode_is_ignored(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $branding = app(BrandingService::class);

        $branding->update(
            ['login_background_size' => 'tidak-ada-mode-ini'],
            null,
            $user->id,
            UploadedFile::fake()->image('latar.jpg'),
        );

        $this->assertSame(
            BrandingService::DEFAULT_SIZE_MODE,
            $branding->current()->login_background_size,
        );
    }

    public function test_replacing_logo_deletes_the_previous_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $branding = app(BrandingService::class);

        $first = UploadedFile::fake()->image('first.png');
        $branding->update([], $first, $user->id);
        $firstPath = $branding->current()->logo_path;

        $second = UploadedFile::fake()->image('second.png');
        $branding->update([], $second, $user->id);

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($branding->current()->logo_path);
    }
}
