<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdminOrHr
{
    /**
     * Allow only administrators and HR users to continue.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['admin', 'hr'], true)) {
            abort(403, 'You are not authorized to access this page.');
        }

        return $next($request);
    }
}
