<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\ApiKeys\ApiKeyService;
use App\Application\Audit\AuditLogger;
use App\Http\Controllers\Concerns\ResolvesTenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiKeyResource;
use App\Infrastructure\Persistence\Eloquent\ApiKey;
use App\Infrastructure\Persistence\Eloquent\Environment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ApiKeyController extends Controller
{
    use ResolvesTenantContext;

    public function __construct(
        private readonly ApiKeyService $apiKeyService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $this->authorize('viewAny', [ApiKey::class, $tenant]);

        $apiKeys = ApiKey::query()
            ->with('environment')
            ->where('tenant_id', $tenant->id)
            ->whereNull('revoked_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => ApiKeyResource::collection($apiKeys),
        ]);
    }

    public function store(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $actor = $this->resolvedActorUser($request);
        $this->authorize('create', [ApiKey::class, $tenant]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', 'max:255'],
            'environment_id' => [
                'nullable',
                'string',
                Rule::exists('environments', 'public_id')->where('tenant_id', $tenant->id),
            ],
            'expires_at' => ['nullable', 'date'],
        ]);

        $environment = null;

        if (! empty($validated['environment_id'])) {
            $environment = Environment::query()
                ->where('public_id', $validated['environment_id'])
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();
        }

        $result = $this->apiKeyService->create(
            tenant: $tenant,
            actor: $actor,
            name: $validated['name'],
            permissions: $validated['permissions'],
            environment: $environment,
            expiresAt: isset($validated['expires_at']) ? new \DateTimeImmutable($validated['expires_at']) : null,
        );

        $apiKey = $result['api_key']->load('environment');

        $this->auditLogger->logFromRequest(
            $request,
            action: 'api_key.created',
            resourceType: 'api_key',
            resourceId: $apiKey->public_id,
            tenantId: $tenant->id,
            summary: ['name' => $apiKey->name, 'permissions' => $apiKey->permissions],
        );

        return response()->json([
            'data' => array_merge(
                (new ApiKeyResource($apiKey))->resolve($request),
                ['plaintext' => $result['plaintext']],
            ),
        ], 201);
    }

    public function destroy(Request $request, string $tenantId, string $apiKeyId): Response
    {
        $tenant = $this->resolvedTenant($request);

        $apiKey = ApiKey::query()
            ->where('public_id', $apiKeyId)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $this->authorize('delete', [$apiKey, $tenant]);

        $this->apiKeyService->revoke($apiKey);

        $this->auditLogger->logFromRequest(
            $request,
            action: 'api_key.revoked',
            resourceType: 'api_key',
            resourceId: $apiKey->public_id,
            tenantId: $tenant->id,
            summary: ['name' => $apiKey->name],
        );

        return response()->noContent();
    }
}
