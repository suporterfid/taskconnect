<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Resources\EnvironmentResource;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EnvironmentController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $this->authorize('viewAny', [Environment::class, $tenant]);

        $environments = $tenant->environments()->orderBy('name')->get();

        return response()->json([
            'data' => EnvironmentResource::collection($environments),
        ]);
    }

    public function store(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $this->authorize('create', [Environment::class, $tenant]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('environments', 'slug')->where('tenant_id', $tenant->id),
            ],
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);

        $environment = $tenant->environments()->create([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        $this->auditLogger->logFromRequest(
            $request,
            action: 'environment.created',
            resourceType: 'environment',
            resourceId: $environment->public_id,
            tenantId: $tenant->id,
            environmentId: $environment->id,
            summary: ['name' => $environment->name, 'slug' => $environment->slug],
        );

        return response()->json([
            'data' => new EnvironmentResource($environment),
        ], 201);
    }

    public function update(Request $request, string $tenantId, string $environmentId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $environment = $this->resolvedEnvironment($request);
        $this->authorize('update', [$environment, $tenant]);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:environments,slug,'.$environment->id.',id,tenant_id,'.$tenant->id],
        ]);

        $environment->fill($validated);
        $environment->save();

        $this->auditLogger->logFromRequest(
            $request,
            action: 'environment.updated',
            resourceType: 'environment',
            resourceId: $environment->public_id,
            tenantId: $tenant->id,
            summary: $validated,
        );

        return response()->json([
            'data' => new EnvironmentResource($environment->fresh()),
        ]);
    }

    public function destroy(Request $request, string $tenantId, string $environmentId): Response
    {
        $tenant = $this->resolvedTenant($request);
        $environment = $this->resolvedEnvironment($request);
        $this->authorize('delete', [$environment, $tenant]);

        $environment->archive();

        $this->auditLogger->logFromRequest(
            $request,
            action: 'environment.archived',
            resourceType: 'environment',
            resourceId: $environment->public_id,
            tenantId: $tenant->id,
        );

        return response()->noContent();
    }

    private function resolvedTenant(Request $request): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return $tenant;
    }

    private function resolvedEnvironment(Request $request): Environment
    {
        /** @var Environment $environment */
        $environment = $request->attributes->get('environment');

        return $environment;
    }
}
