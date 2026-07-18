<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Audit\AuditLogger;
use App\Application\Tenancy\TenantProvisioner;
use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantProvisioner $tenantProvisioner,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tenant::class);

        /** @var \App\Infrastructure\Persistence\Eloquent\User $user */
        $user = $request->user();

        $query = Tenant::query()->orderBy('name');

        if (! $user->isPlatformAdmin()) {
            $query->whereHas('memberships', fn ($membershipQuery) => $membershipQuery
                ->where('user_id', $user->id));
        }

        $tenants = $query->get();

        return response()->json([
            'data' => TenantResource::collection($tenants),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Tenant::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:tenants,slug'],
        ]);

        $tenant = $this->tenantProvisioner->create(
            name: $validated['name'],
            slug: $validated['slug'] ?? null,
        );

        $this->auditLogger->logFromRequest(
            $request,
            action: 'tenant.created',
            resourceType: 'tenant',
            resourceId: $tenant->public_id,
            tenantId: $tenant->id,
            summary: ['name' => $tenant->name, 'slug' => $tenant->slug],
        );

        return response()->json([
            'data' => new TenantResource($tenant),
        ], 201);
    }

    public function show(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $this->authorize('view', $tenant);

        return response()->json([
            'data' => new TenantResource($tenant),
        ]);
    }

    public function update(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:tenants,slug,'.$tenant->id],
        ]);

        $tenant->fill($validated);
        $tenant->save();

        $this->auditLogger->logFromRequest(
            $request,
            action: 'tenant.updated',
            resourceType: 'tenant',
            resourceId: $tenant->public_id,
            tenantId: $tenant->id,
            summary: $validated,
        );

        return response()->json([
            'data' => new TenantResource($tenant->fresh()),
        ]);
    }

    private function resolvedTenant(Request $request): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return $tenant;
    }
}
