<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces Cabang Scope (Single/Multiple/All Branch) on every request that
 * touches a specific branch — PRD §6, SECURITY §Authorization (AUTH-02/03/04).
 *
 * This is checked in addition to (not instead of) the BranchScope Eloquent
 * global scope: this middleware catches branch_id passed explicitly via
 * route parameter or request body/query before a query even runs.
 */
class EnsureBranchScope
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $allowedBranchIds = $user->allowedBranchIds();

        // null = "All Branch" scope — unrestricted.
        if ($allowedBranchIds === null) {
            return $next($request);
        }

        $requestedBranchId = $request->route('branch') ?? $request->input('branch_id');

        if ($requestedBranchId !== null && ! in_array((int) $requestedBranchId, $allowedBranchIds, true)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        return $next($request);
    }
}
