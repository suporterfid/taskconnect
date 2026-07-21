<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Audit\AuditLogger;
use App\Application\Secrets\SecretService;
use App\Application\Tenancy\EnvironmentGuard;
use App\Http\Controllers\Concerns\ResolvesTenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\SecretResource;
use App\Infrastructure\Persistence\Eloquent\Secret;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class SecretController extends Controller
{
    use ResolvesTenantContext;

    public function __construct(
        private readonly SecretService $secretService,
        private readonly AuditLogger $auditLogger,
        private readonly EnvironmentGuard $environmentGuard,
    ) {}

    public function index(Request $request, string $tenantId, string $environmentId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $environment = $this->resolvedEnvironment($request);
        $this->authorize('viewAny', [Secret::class, $tenant]);

        $secrets = Secret::query()
            ->where('tenant_id', $tenant->id)
            ->where('environment_id', $environment->id)
            ->notArchived()
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => SecretResource::collection($secrets),
        ]);
    }

    public function store(Request $request, string $tenantId, string $environmentId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $environment = $this->resolvedEnvironment($request);
        $actor = $this->resolvedActorUser($request);
        $this->authorize('create', [Secret::class, $tenant]);
        $this->environmentGuard->assertActive($environment);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('secrets', 'name')
                    ->where('tenant_id', $tenant->id)
                    ->where('environment_id', $environment->id)
                    ->whereNull('archived_at'),
            ],
            'value' => ['required', 'string', 'max:65535'],
        ]);

        $secret = $this->secretService->create(
            tenant: $tenant,
            environment: $environment,
            actor: $actor,
            name: $validated['name'],
            plaintext: $validated['value'],
        );

        $this->auditLogger->logFromRequest(
            $request,
            action: 'secret.created',
            resourceType: 'secret',
            resourceId: $secret->public_id,
            tenantId: $tenant->id,
            summary: ['name' => $secret->name, 'version' => $secret->version],
        );

        return response()->json([
            'data' => array_merge(
                (new SecretResource($secret))->resolve($request),
                ['plaintext' => $validated['value']],
            ),
        ], 201);
    }

    public function show(Request $request, string $tenantId, string $environmentId, string $secretId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $environment = $this->resolvedEnvironment($request);
        $secret = $this->findSecret($tenant->id, $environment->id, $secretId);
        $this->authorize('view', [$secret, $tenant]);

        return response()->json([
            'data' => new SecretResource($secret),
        ]);
    }

    public function rotate(Request $request, string $tenantId, string $environmentId, string $secretId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $environment = $this->resolvedEnvironment($request);
        $actor = $this->resolvedActorUser($request);
        $secret = $this->findSecret($tenant->id, $environment->id, $secretId);
        $this->authorize('rotate', [$secret, $tenant]);

        $validated = $request->validate([
            'value' => ['required', 'string', 'max:65535'],
        ]);

        $secret = $this->secretService->rotate($secret, $actor, $validated['value']);

        $this->auditLogger->logFromRequest(
            $request,
            action: 'secret.rotated',
            resourceType: 'secret',
            resourceId: $secret->public_id,
            tenantId: $tenant->id,
            summary: ['name' => $secret->name, 'version' => $secret->version],
        );

        return response()->json([
            'data' => array_merge(
                (new SecretResource($secret))->resolve($request),
                ['plaintext' => $validated['value']],
            ),
        ]);
    }

    public function destroy(Request $request, string $tenantId, string $environmentId, string $secretId): Response
    {
        $tenant = $this->resolvedTenant($request);
        $environment = $this->resolvedEnvironment($request);
        $actor = $this->resolvedActorUser($request);
        $secret = $this->findSecret($tenant->id, $environment->id, $secretId);
        $this->authorize('delete', [$secret, $tenant]);

        $this->secretService->archive($secret, $actor);

        $this->auditLogger->logFromRequest(
            $request,
            action: 'secret.archived',
            resourceType: 'secret',
            resourceId: $secret->public_id,
            tenantId: $tenant->id,
            summary: ['name' => $secret->name],
        );

        return response()->noContent();
    }

    private function findSecret(int $tenantId, int $environmentId, string $secretId): Secret
    {
        return Secret::query()
            ->where('public_id', $secretId)
            ->where('tenant_id', $tenantId)
            ->where('environment_id', $environmentId)
            ->notArchived()
            ->firstOrFail();
    }
}
