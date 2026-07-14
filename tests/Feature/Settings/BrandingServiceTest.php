<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Services\Settings\BrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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
