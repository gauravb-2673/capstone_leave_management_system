<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Allow only administrators to continue.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the currently authenticated user.
        $user = $request->user();

        // Reject guests and users without the admin role.
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'You are not authorized to access this page.');
        }

        // Allow administrators to continue to the requested page.
        return $next($request);
    }
}
