<?php

namespace App\Http\Middleware;

use App\Application\ApiKeys\ApiKeyService;
use App\Application\GrandpaSson\IntrospectionClientInterface;
use App\Auth\ApiKeyActor;
use App\Auth\GrandpaSsonActor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts Sanctum session authentication, Bearer API keys (tc_*),
 * or GrandpaSSOn opaque bearer tokens when inbound mode is enabled (R8).
 */
class AuthenticateApiKeyOrSanctum
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService,
        private readonly IntrospectionClientInterface $introspection,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        $token = $this->extractBearerToken($request);

        if ($token !== null && str_starts_with($token, 'tc_')) {
            $apiKey = $this->apiKeyService->authenticate($token);

            if ($apiKey === null) {
                abort(401);
            }

            $request->attributes->set('api_key', $apiKey);
            Auth::setUser(new ApiKeyActor($apiKey));

            return $next($request);
        }

        if ($token !== null && (bool) config('grandpasson.inbound_enabled', false)) {
            $result = $this->introspection->introspect($token);
            if ($result->active) {
                $actor = new GrandpaSsonActor($result, hash('sha256', $token));
                $request->attributes->set('grandpasson', $result);
                Auth::setUser($actor);

                return $next($request);
            }
        }

        foreach (['sanctum', 'web'] as $guard) {
            $user = Auth::guard($guard)->user();

            if ($user !== null) {
                Auth::setUser($user);

                return $next($request);
            }
        }

        abort(401);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if ($header === null || ! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }
}
