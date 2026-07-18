<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Tasks\RunLifecycleService;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskRunAttemptResource;
use App\Http\Resources\TaskRunResource;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TaskRunController extends Controller
{
    public function __construct(private readonly RunLifecycleService $runLifecycle) {}

    public function index(Request $request, string $tenantId, string $environmentId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);
        $this->authorize('viewAny', [TaskRun::class, $tenant]);

        $runs = TaskRun::query()
            ->where('tenant_id', $tenant->id)
            ->where('environment_id', $environment->id)
            ->orderByDesc('created_at')
            ->with('task')
            ->limit(100)
            ->get();

        return response()->json(['data' => TaskRunResource::collection($runs)]);
    }

    public function show(Request $request, string $tenantId, string $environmentId, string $runId): JsonResponse
    {
        $run = $this->resolveRun($request);

        return response()->json(['data' => new TaskRunResource($run)]);
    }

    public function attempts(Request $request, string $tenantId, string $environmentId, string $runId): JsonResponse
    {
        $run = $this->resolveRun($request);

        return response()->json(['data' => TaskRunAttemptResource::collection($run->attempts)]);
    }

    public function cancel(Request $request, string $tenantId, string $environmentId, string $runId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $run = $this->resolveRun($request);
        $this->authorize('cancel', [$run, $tenant]);

        $run = $this->runLifecycle->cancel($run);

        return response()->json(['data' => new TaskRunResource($run)]);
    }

    public function retry(Request $request, string $tenantId, string $environmentId, string $runId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $run = $this->resolveRun($request);
        $this->authorize('retry', [$run, $tenant]);

        $this->runLifecycle->manualRetry($run);

        return response()->json(['data' => new TaskRunResource($run->fresh(['task', 'attempts']))], 202);
    }

    private function resolveRun(Request $request): TaskRun
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);

        $run = TaskRun::query()
            ->where('public_id', $request->route('runId'))
            ->where('tenant_id', $tenant->id)
            ->where('environment_id', $environment->id)
            ->with(['task', 'attempts'])
            ->firstOrFail();

        $this->authorize('view', [$run, $tenant]);

        return $run;
    }

    private function tenant(Request $request): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return $tenant;
    }

    private function environment(Request $request): Environment
    {
        /** @var Environment $environment */
        $environment = $request->attributes->get('environment');

        return $environment;
    }
}
