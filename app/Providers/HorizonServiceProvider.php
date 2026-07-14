<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     * Restricted to Admin Sistem (the role that already owns
     * `security_config.manage` — infra/queue monitoring is a system-config
     * concern, not something Manajer/Bendahara/Pengawas need).
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return $user?->hasRole('admin_sistem') ?? false;
        });
    }
}
