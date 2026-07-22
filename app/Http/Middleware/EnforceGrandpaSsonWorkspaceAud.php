<?php

namespace App\Http\Middleware;

use App\Application\Audit\AuditLogger;
use App\Application\GrandpaSson\IntrospectionClientInterface;
use App\Auth\GrandpaSsonActor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When inbound GrandpaSSOn mode is enabled and the actor is a GrandpaSsonActor,
 * require tasks:write scope and aud covering the current workspace (R8).
 * Sanctum / API-key actors are unchanged (dual-mode).
 */
class EnforceGrandpaSsonWorkspaceAud
{
    public function __construct(
        private readonly IntrospectionClientInterface $introspection,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('grandpasson.inbound_enabled', false)) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user instanceof GrandpaSsonActor) {
            return $next($request);
        }

        $environment = $request->attributes->get('environment');
        $workspaceId = is_object($environment) ? (string) ($environment->public_id ?? '') : '';
        $writeScope = (string) config('grandpasson.write_scope', 'tasks:write');
        $result = $user->introspection;

        if (! $result->active || ! $result->hasScope($writeScope) || $workspaceId === '' || ! $result->audienceIncludes($workspaceId)) {
            $tenant = $request->attributes->get('tenant');
            $this->auditLogger->log(
                action: 'grandpasson.workspace_denied',
                resourceType: 'workspace',
                resourceId: $workspaceId !== '' ? $workspaceId : null,
                tenantId: is_object($tenant) ? ($tenant->id ?? null) : null,
                environmentId: is_object($environment) ? ($environment->id ?? null) : null,
                summary: [
                    'reason' => 'aud_or_scope_mismatch',
                    'required_scope' => $writeScope,
                    'scopes' => $result->scopes,
                    'audiences' => $result->audiences,
                    'token_fingerprint' => $user->tokenFingerprint,
                ],
                actor: null,
            );

            abort(403, 'GrandpaSSOn token is not authorized for this workspace.');
        }

        return $next($request);
    }
}
