<?php

namespace App\Http\Middleware;

use App\Infrastructure\Persistence\Eloquent\ApiKey;
use App\Infrastructure\Persistence\Eloquent\IdempotencyKey;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawKey = $request->header('Idempotency-Key');

        if (! is_string($rawKey) || trim($rawKey) === '') {
            return $next($request);
        }

        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant');

        if ($tenant === null) {
            abort(400, 'Tenant context is required for idempotent requests.');
        }

        $key = mb_substr(trim($rawKey), 0, 255);
        $route = $request->method().' '.($request->route()?->uri() ?? $request->path());
        $requestHash = hash('sha256', $this->requestFingerprint($request));

        $existing = IdempotencyKey::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', $key)
            ->where('route', $route)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing !== null) {
            if (! hash_equals($existing->request_hash, $requestHash)) {
                abort(409, 'Idempotency key reused with a different payload.');
            }

            return response()->json($existing->response_body, $existing->response_code);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response->getStatusCode() >= 500) {
            return $response;
        }

        $responseBody = json_decode($response->getContent(), true);
        if (! is_array($responseBody)) {
            $responseBody = [];
        }

        $user = $request->user();
        $userId = $user instanceof User ? $user->id : null;

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        $attributes = [
            'tenant_id' => $tenant->id,
            'user_id' => $userId,
            'api_key_id' => $apiKey?->id,
            'key' => $key,
            'route' => $route,
            'request_hash' => $requestHash,
            'response_code' => $response->getStatusCode(),
            'response_body' => $responseBody,
            'created_at' => now(),
            'expires_at' => now()->addHours((int) config('retention.api_idempotency_hours', 24)),
        ];

        try {
            IdempotencyKey::query()->create($attributes);
        } catch (QueryException $exception) {
            $stored = IdempotencyKey::query()
                ->where('tenant_id', $tenant->id)
                ->where('key', $key)
                ->where('route', $route)
                ->where('expires_at', '>', now())
                ->first();

            if ($stored === null) {
                throw $exception;
            }

            if (! hash_equals($stored->request_hash, $requestHash)) {
                abort(409, 'Idempotency key reused with a different payload.');
            }

            return response()->json($stored->response_body, $stored->response_code);
        }

        return $response;
    }

    private function requestFingerprint(Request $request): string
    {
        $content = $request->getContent();

        if (is_string($content) && $content !== '') {
            return $content;
        }

        return (string) json_encode($request->all());
    }
}
