<?php

namespace App\Http\Middleware;

use App\Support\Installer\EnvFileWriter;
use App\Support\Installer\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prepended in front of the whole 'web' group (bootstrap/app.php) so it
 * runs before EncryptCookies/StartSession. Solves the installer's chicken-
 * and-egg problem: those two middleware normally need APP_KEY and a working
 * session store (DB/Redis) before a single byte of HTML can render — but on
 * a fresh shared-hosting deploy neither exists yet, so without this, even
 * the installer's own welcome page would crash.
 *
 * While not yet installed, this forces file-based session/cache/queue
 * (only storage/ writability is required for that) and self-heals a blank
 * APP_KEY, then sends any request outside /install to the installer.
 */
class BootstrapInstallerEnvironment
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (InstallState::isInstalled()) {
            return $next($request);
        }

        config([
            'session.driver' => 'file',
            'cache.default' => 'file',
            'queue.default' => 'sync',
        ]);

        $this->ensureAppKey();

        if (! $request->is('install') && ! $request->is('install/*')) {
            return redirect()->route('install.welcome');
        }

        return $next($request);
    }

    private function ensureAppKey(): void
    {
        if (filled(config('app.key'))) {
            return;
        }

        $key = 'base64:'.base64_encode(random_bytes(32));

        config(['app.key' => $key]);

        if (EnvFileWriter::isWritable()) {
            EnvFileWriter::set(['APP_KEY' => $key]);
        }
    }
}
