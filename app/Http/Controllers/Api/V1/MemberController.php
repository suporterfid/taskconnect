<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Audit\AuditLogger;
use App\Application\Members\MemberService;
use App\Domain\Shared\Enums\TenantRole;
use App\Http\Controllers\Concerns\ResolvesTenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    use ResolvesTenantContext;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly MemberService $memberService,
    ) {}

    public function index(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $this->authorize('viewAny', [TenantMembership::class, $tenant]);

        $members = TenantMembership::query()
            ->with('user')
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => MemberResource::collection($members),
        ]);
    }

    public function store(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $this->authorize('create', [TenantMembership::class, $tenant]);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::enum(TenantRole::class)],
        ]);

        $role = $validated['role'] instanceof TenantRole
            ? $validated['role']
            : TenantRole::from($validated['role']);

        $result = $this->memberService->invite(
            $tenant,
            $validated['email'],
            $role,
            $validated['name'] ?? null,
        );

        $membership = $result['membership'];

        $this->auditLogger->logFromRequest(
            $request,
            action: 'member.invited',
            resourceType: 'tenant_membership',
            resourceId: $membership->public_id,
            tenantId: $tenant->id,
            summary: [
                'email' => $membership->user?->email,
                'role' => $membership->role?->value ?? $membership->role,
                'created_user' => $result['created_user'],
            ],
        );

        return response()->json([
            'data' => new MemberResource($membership),
        ], 201);
    }

    public function update(Request $request, string $tenantId, string $memberId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $membership = $this->findMembership($tenant->id, $memberId);
        $this->authorize('update', [$membership, $tenant]);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(TenantRole::class)],
        ]);

        $role = $validated['role'] instanceof TenantRole
            ? $validated['role']
            : TenantRole::from($validated['role']);

        $previousRole = $membership->role?->value ?? $membership->role;
        $membership = $this->memberService->updateRole(
            $tenant,
            $membership,
            $role,
        );

        $this->auditLogger->logFromRequest(
            $request,
            action: 'member.role_changed',
            resourceType: 'tenant_membership',
            resourceId: $membership->public_id,
            tenantId: $tenant->id,
            summary: [
                'email' => $membership->user?->email,
                'from' => $previousRole,
                'to' => $membership->role?->value ?? $membership->role,
            ],
        );

        return response()->json([
            'data' => new MemberResource($membership),
        ]);
    }

    public function destroy(Request $request, string $tenantId, string $memberId): Response
    {
        $tenant = $this->resolvedTenant($request);
        $membership = $this->findMembership($tenant->id, $memberId);
        $this->authorize('delete', [$membership, $tenant]);

        $actor = $this->resolvedActorUser($request);
        $summary = [
            'email' => $membership->user?->email,
            'role' => $membership->role?->value ?? $membership->role,
        ];

        $this->memberService->remove($tenant, $membership, $actor);

        $this->auditLogger->logFromRequest(
            $request,
            action: 'member.removed',
            resourceType: 'tenant_membership',
            resourceId: $memberId,
            tenantId: $tenant->id,
            summary: $summary,
        );

        return response()->noContent();
    }

    private function findMembership(int $tenantId, string $memberId): TenantMembership
    {
        return TenantMembership::query()
            ->with('user')
            ->where('tenant_id', $tenantId)
            ->where('public_id', $memberId)
            ->firstOrFail();
    }
}
