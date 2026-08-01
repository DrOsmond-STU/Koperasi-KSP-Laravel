<?php

namespace App\Http\Middleware;

use App\Support\Installer\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the /install/* routes once setup is done — re-running the wizard
 * after go-live would let anyone reach a fresh "create admin account" form.
 */
class EnsureNotInstalled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (InstallState::isInstalled()) {
            return redirect()->route('login')->with('status', 'Aplikasi sudah terinstal.');
        }

        return $next($request);
    }
}
