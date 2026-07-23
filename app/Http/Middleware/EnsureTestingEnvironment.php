<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates test-only fixture endpoints (e.g. E2eFixtureController) to local/testing.
 * Never reachable on a production/shared-hosting deploy.
 */
class EnsureTestingEnvironment
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment(['local', 'testing'])) {
            abort(404);
        }

        return $next($request);
    }
}
