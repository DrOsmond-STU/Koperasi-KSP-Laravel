<?php

use App\Http\Middleware\BootstrapInstallerEnvironment;
use App\Http\Middleware\EnsureBranchScope;
use App\Http\Middleware\EnsureHasLinkedMember;
use App\Http\Middleware\EnsureMfaIsEnabledForInternalRoles;
use App\Http\Middleware\EnsureNotInstalled;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Import massal Master Anggota - lihat routes/member-import.php.
            require __DIR__.'/../routes/member-import.php';
            // Master Kategori Produk Simpanan.
            require __DIR__.'/../routes/master-kategori-simpanan.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'branch.scope' => EnsureBranchScope::class,
            'mfa.required' => EnsureMfaIsEnabledForInternalRoles::class,
            'active.user' => EnsureUserIsActive::class,
            'member.portal' => EnsureHasLinkedMember::class,
            'not.installed' => EnsureNotInstalled::class,
        ]);
        $middleware->encryptCookies(except: ['theme', 'sidebar_collapsed']);
        // Must run before EncryptCookies/StartSession — see class docblock.
        $middleware->prependToGroup('web', BootstrapInstallerEnvironment::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
