<?php

namespace App\Providers;

use App\Services\Settings\BrandingService;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BrandingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SECURITY.md §Authentication: minimum 10 karakter (anggota) / 12
        // (role internal — ditegakkan tambahan saat pembuatan akun staf),
        // wajib huruf besar/kecil + angka, dicek terhadap daftar bocor.
        Password::defaults(fn () => Password::min(10)
            ->mixedCase()
            ->numbers()
            ->uncompromised()
        );

        // Nama & logo aplikasi (kustomisasi koperasi) tersedia di semua view
        // sebagai $branding — dipakai layout login & topbar (03_DESIGN.md).
        View::composer('*', function ($view): void {
            $branding = app(BrandingService::class)->current();

            $view->with('branding', [
                'app_name' => $branding->app_name,
                'logo_url' => app(BrandingService::class)->logoUrl(),
            ]);
        });

        // DEPLOYMENT.md §6/§8: the /up health check must actually verify
        // MySQL, Redis, and storage — not just "the app booted". Any
        // exception thrown here fails the health check (Laravel's
        // ApplicationBuilder catches it and reports 500 with the message).
        Event::listen(function (DiagnosingHealth $event): void {
            DB::connection()->getPdo();

            Redis::connection()->ping();

            $probe = 'health-check/.probe';
            Storage::disk('local')->put($probe, (string) now()->timestamp);
            Storage::disk('local')->delete($probe);
        });
    }
}
