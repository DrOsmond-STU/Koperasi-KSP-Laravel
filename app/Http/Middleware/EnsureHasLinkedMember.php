<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for Portal Anggota (/portal/*) — every action there resolves "which
 * member's data to show" from `$request->user()->member`, never from
 * request input, so a user with no linked Member record must be blocked
 * here rather than reaching a controller that has nothing to scope to.
 */
class EnsureHasLinkedMember
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->member, 403, 'Akun Anda belum terhubung ke data anggota — hubungi Admin Sistem.');

        return $next($request);
    }
}
