<?php

namespace App\Application\Audit;

use App\Infrastructure\Persistence\Eloquent\AuditLog;
use App\Infrastructure\Persistence\Eloquent\Environment;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /** @var list<string> */
    private const REDACTED_KEYS = [
        'password',
        'token',
        'secret',
        'authorization',
        'api_key',
    ];

    public function log(
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        ?int $tenantId = null,
        ?int $environmentId = null,
        ?array $summary = null,
        ?Authenticatable $actor = null,
        ?string $requestId = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'environment_id' => $environmentId,
            'actor_user_id' => $actor?->getAuthIdentifier(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'request_id' => $requestId,
            'summary_json' => $this->redact($summary),
        ]);
    }

    public function logFromRequest(
        Request $request,
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        ?int $tenantId = null,
        ?int $environmentId = null,
        ?array $summary = null,
    ): AuditLog {
        $environment = $request->attributes->get('environment');
        $resolvedEnvironmentId = $environmentId
            ?? ($environment instanceof Environment ? $environment->id : null);

        return $this->log(
            action: $action,
            resourceType: $resourceType,
            resourceId: $resourceId,
            tenantId: $tenantId,
            environmentId: $resolvedEnvironmentId,
            summary: $summary,
            actor: Auth::user(),
            requestId: $request->attributes->get('request_id'),
        );
    }

    /**
     * @param  array<string, mixed>|null  $summary
     * @return array<string, mixed>|null
     */
    public function redact(?array $summary): ?array
    {
        if ($summary === null) {
            return null;
        }

        $redacted = [];

        foreach ($summary as $key => $value) {
            if ($this->shouldRedact((string) $key)) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = $this->redact($value);

                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

    private function shouldRedact(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::REDACTED_KEYS as $redactedKey) {
            if (str_contains($normalized, $redactedKey)) {
                return true;
            }
        }

        return false;
    }
}
