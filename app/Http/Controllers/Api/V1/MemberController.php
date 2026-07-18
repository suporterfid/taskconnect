<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesTenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    use ResolvesTenantContext;

    public function index(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $this->authorize('view', $tenant);

        $members = TenantMembership::query()
            ->with('user')
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => MemberResource::collection($members),
        ]);
    }
}
