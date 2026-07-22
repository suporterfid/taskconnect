<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesTenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Infrastructure\Persistence\Eloquent\AuditLog;
use App\Infrastructure\Persistence\Eloquent\Environment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ResolvesTenantContext;

    public function index(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $this->authorize('view', $tenant);

        $perPage = min(100, max(1, (int) $request->integer('per_page', 50)));

        $logs = AuditLog::query()
            ->with(['actor', 'environment'])
            ->where('tenant_id', $tenant->id)
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')->toString()))
            ->when($request->filled('workspace_id'), function ($query) use ($request, $tenant): void {
                $workspacePublicId = $request->string('workspace_id')->toString();
                $environmentId = Environment::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('public_id', $workspacePublicId)
                    ->value('id');

                if ($environmentId === null) {
                    $query->whereRaw('0 = 1');

                    return;
                }

                $query->where('environment_id', $environmentId);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($perPage)
            ->get();

        return response()->json([
            'data' => AuditLogResource::collection($logs),
        ]);
    }
}
