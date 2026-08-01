<?php

namespace App\Actions\Fortify;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

/**
 * Anggota-only accounts land on Portal Anggota after login, not the
 * staff/admin dashboard (config('fortify.home')) — everyone else keeps
 * Fortify's normal redirect unchanged. "Anggota-only" = has the `anggota`
 * role and no other (staff/admin) role, and has a linked Member record to
 * actually use the portal with; a staff account that also happens to be a
 * member still goes to the admin dashboard.
 */
class RoleAwareLoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $user = $request->user();

        if ($user?->isPortalOnlyMember()) {
            return redirect()->intended(route('portal.dashboard'));
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
