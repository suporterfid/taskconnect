<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\ApiKeys\ApiKeyService;
use App\Application\Audit\AuditLogger;
use App\Application\Tenancy\EnvironmentGuard;
use App\Http\Controllers\Concerns\ResolvesTenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiKeyResource;
use App\Infrastructure\Persistence\Eloquent\ApiKey;
use App\Infrastructure\Persistence\Eloquent\Environment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApiKeyController extends Controller
{
    use ResolvesTenantContext;

    public function __construct(
        private readonly ApiKeyService $apiKeyService,
        private readonly AuditLogger $auditLogger,
        private readonly EnvironmentGuard $environmentGuard,
    ) {}

    public function index(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $this->authorize('viewAny', [ApiKey::class, $tenant]);

        $apiKeys = ApiKey::query()
            ->with('environment')
            ->where('tenant_id', $tenant->id)
            ->orderByRaw('CASE WHEN revoked_at IS NULL THEN 0 ELSE 1 END')
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

        $validated = $request->validate($this->payloadRules($tenant->id, requiredName: true));

        $environment = null;

        if (! empty($validated['environment_id'])) {
            $environment = Environment::query()
                ->where('public_id', $validated['environment_id'])
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            $this->environmentGuard->assertActive($environment);
        }

        $result = $this->apiKeyService->create(
            tenant: $tenant,
            actor: $actor,
            name: $validated['name'],
            permissions: $validated['permissions'],
            environment: $environment,
            expiresAt: array_key_exists('expires_at', $validated) && $validated['expires_at'] !== null
                ? new \DateTimeImmutable($validated['expires_at'])
                : null,
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

    public function update(Request $request, string $tenantId, string $apiKeyId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);

        $apiKey = ApiKey::query()
            ->where('public_id', $apiKeyId)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $this->authorize('update', [$apiKey, $tenant]);

        if ($apiKey->isRevoked()) {
            throw ValidationException::withMessages([
                'api_key' => ['Revoked API keys cannot be updated.'],
            ]);
        }

        $validated = $request->validate($this->payloadRules($tenant->id, requiredName: false));

        $attributes = [];

        if (array_key_exists('name', $validated)) {
            $attributes['name'] = $validated['name'];
        }

        if (array_key_exists('permissions', $validated)) {
            $attributes['permissions'] = $validated['permissions'];
        }

        if (array_key_exists('expires_at', $validated)) {
            $attributes['expires_at'] = $validated['expires_at'] !== null
                ? new \DateTimeImmutable($validated['expires_at'])
                : null;
        }

        $apiKey = $this->apiKeyService->update($apiKey, $attributes)->load('environment');

        $this->auditLogger->logFromRequest(
            $request,
            action: 'api_key.updated',
            resourceType: 'api_key',
            resourceId: $apiKey->public_id,
            tenantId: $tenant->id,
            summary: $validated,
        );

        return response()->json([
            'data' => new ApiKeyResource($apiKey),
        ]);
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

    /**
     * @return array<string, mixed>
     */
    private function payloadRules(int $tenantId, bool $requiredName): array
    {
        $nameRule = $requiredName ? ['required', 'string', 'max:255'] : ['sometimes', 'string', 'max:255'];
        $permissionsRule = $requiredName
            ? ['required', 'array', 'min:1']
            : ['sometimes', 'array', 'min:1'];

        $rules = [
            'name' => $nameRule,
            'permissions' => $permissionsRule,
            'permissions.*' => ['required', 'string', Rule::in(ApiKeyService::ALLOWED_PERMISSIONS)],
            'expires_at' => ['nullable', 'date'],
        ];

        if ($requiredName) {
            $rules['environment_id'] = [
                'nullable',
                'string',
                Rule::exists('environments', 'public_id')->where('tenant_id', $tenantId),
            ];
        }

        return $rules;
    }
}
