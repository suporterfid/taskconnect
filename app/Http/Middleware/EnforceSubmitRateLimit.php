<?php

namespace App\Http\Middleware;

use App\Application\RateLimiting\DatabaseRateLimiter;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DB-backed rate limit for task/pipeline submission endpoints (R15).
 */
final class EnforceSubmitRateLimit
{
    public function __construct(
        private readonly DatabaseRateLimiter $limiter,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant');
        /** @var Environment|null $environment */
        $environment = $request->attributes->get('environment');

        if ($tenant === null || $environment === null) {
            return $next($request);
        }

        $this->limiter->hitOrFail(
            $this->limiter->bucketKeyForWorkspace($tenant, $environment),
            $this->limiter->limitForWorkspace($tenant, $environment),
            (int) config('scheduler.submit_rate_limit_window_seconds', 60),
        );

        return $next($request);
    }
}
