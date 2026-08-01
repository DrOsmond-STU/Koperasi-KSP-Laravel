<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Settings\BrandingService;
use App\Services\Settings\PrintSettingsService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        $this->app->singleton(PrintSettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // admin_sistem adalah superuser aplikasi — selalu lolos setiap
        // pengecekan otorisasi (authorize()/@can/menu sidebar), termasuk
        // permission yang belum/tidak pernah digrant lewat RolePermissionSeeder.
        // Ini menghindari daftar permission admin_sistem harus disinkronkan
        // manual tiap kali modul baru ditambah.
        Gate::before(fn (User $user, string $ability): ?bool => $user->hasRole('admin_sistem') ? true : null);

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
        // $theme (preferensi tampilan 'a'/'b', cookie 'theme' — lihat
        // bootstrap/app.php utk pengecualian dari EncryptCookies) juga
        // di-share di sini supaya berlaku di layout login (belum auth)
        // maupun layout aplikasi (sudah auth) lewat satu mekanisme yang sama.
        View::composer('*', function ($view): void {
            $branding = app(BrandingService::class)->current();

            $view->with('branding', [
                'app_name' => $branding->app_name,
                'logo_url' => app(BrandingService::class)->logoUrl(),
            ]);

            // Shared letterhead/paper layout for every cetakan (PDF views
            // render through this same composer via Pdf::loadView()).
            $view->with('printSettings', app(PrintSettingsService::class)->current());

            $theme = request()->cookie('theme');
            $view->with('theme', in_array($theme, ['a', 'b'], true) ? $theme : 'a');

            // Desktop sidebar collapse preference (icon-only rail) — same
            // cookie-based, server-rendered persistence as $theme above, so
            // there's no flash of the wrong sidebar width on load.
            $view->with('sidebarCollapsed', request()->cookie('sidebar_collapsed') === '1');
        });

        // SECURITY.md §Audit log: login/logout tidak lolos lewat hook
        // updated() milik Auditable manapun (bukan perubahan kolom pada
        // model apa pun) — dicatat manual di sini, satu-satunya tempat
        // event otentikasi Laravel ditangani di aplikasi ini.
        Event::listen(function (Login $event): void {
            $this->recordAuthEvent($event->user, 'login');
        });

        Event::listen(function (Logout $event): void {
            if ($event->user) {
                $this->recordAuthEvent($event->user, 'logout');
            }
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

    private function recordAuthEvent(User $user, string $action): void
    {
        AuditLog::query()->create([
            'actor_id' => $user->id,
            'actor_role' => $user->getRoleNames()->implode(','),
            'branch_id' => null,
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'before' => null,
            'after' => null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
