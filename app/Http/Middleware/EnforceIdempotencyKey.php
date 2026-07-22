<?php

namespace App\Http\Middleware;

use App\Infrastructure\Persistence\Eloquent\ApiKey;
use App\Infrastructure\Persistence\Eloquent\Environment;
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
            abort(422, 'Idempotency-Key header is required.');
        }

        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant');

        if ($tenant === null) {
            abort(400, 'Tenant context is required for idempotent requests.');
        }

        /** @var Environment|null $environment */
        $environment = $request->attributes->get('environment');

        if ($environment === null) {
            abort(400, 'Workspace (environment) context is required for idempotent requests.');
        }

        $key = mb_substr(trim($rawKey), 0, 255);
        $route = $request->method().' '.($request->route()?->uri() ?? $request->path());
        $requestHash = hash('sha256', $this->requestFingerprint($request));
        $ttlHours = max(1, (int) config('retention.api_idempotency_hours', 24));

        $existing = $this->findActive($tenant->id, $environment->id, $key, $route);

        if ($existing !== null) {
            if (! hash_equals($existing->request_hash, $requestHash)) {
                abort(409, 'Idempotency key reused with a different payload.');
            }

            return $this->replayResponse($existing);
        }

        // Allow reuse after the TTL window even if prune has not run yet.
        $this->deleteExpired($tenant->id, $environment->id, $key, $route);

        /** @var Response $response */
        $response = $next($request);

        if ($response->getStatusCode() >= 500) {
            return $response;
        }

        // Do not cache client/validation errors — caller may retry with a fixed payload.
        if ($response->getStatusCode() >= 400) {
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
            'environment_id' => $environment->id,
            'user_id' => $userId,
            'api_key_id' => $apiKey?->id,
            'key' => $key,
            'route' => $route,
            'request_hash' => $requestHash,
            'response_code' => $response->getStatusCode(),
            'response_body' => $responseBody,
            'created_at' => now(),
            'expires_at' => now()->addHours($ttlHours),
        ];

        try {
            IdempotencyKey::query()->create($attributes);
        } catch (QueryException $exception) {
            $stored = $this->findActive($tenant->id, $environment->id, $key, $route);

            if ($stored === null) {
                $this->deleteExpired($tenant->id, $environment->id, $key, $route);

                try {
                    IdempotencyKey::query()->create($attributes);

                    return $response;
                } catch (QueryException) {
                    $stored = $this->findActive($tenant->id, $environment->id, $key, $route);
                }
            }

            if ($stored === null) {
                throw $exception;
            }

            if (! hash_equals($stored->request_hash, $requestHash)) {
                abort(409, 'Idempotency key reused with a different payload.');
            }

            return $this->replayResponse($stored);
        }

        return $response;
    }

    private function findActive(int $tenantId, int $environmentId, string $key, string $route): ?IdempotencyKey
    {
        return IdempotencyKey::query()
            ->where('tenant_id', $tenantId)
            ->where('environment_id', $environmentId)
            ->where('key', $key)
            ->where('route', $route)
            ->where('expires_at', '>', now())
            ->first();
    }

    private function deleteExpired(int $tenantId, int $environmentId, string $key, string $route): void
    {
        IdempotencyKey::query()
            ->where('tenant_id', $tenantId)
            ->where('environment_id', $environmentId)
            ->where('key', $key)
            ->where('route', $route)
            ->where('expires_at', '<=', now())
            ->delete();
    }

    private function replayResponse(IdempotencyKey $existing): Response
    {
        $status = (int) $existing->response_code;

        // Spec R2: idempotent replay of a created resource returns 200.
        if ($status === 201) {
            $status = 200;
        }

        return response()->json($existing->response_body, $status);
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
