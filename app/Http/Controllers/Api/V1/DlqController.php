<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Tasks\DlqService;
use App\Domain\Execution\InvalidStateTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskRunResource;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DlqController extends Controller
{
    public function __construct(
        private readonly DlqService $dlq,
    ) {
    }

    public function index(Request $request, string $tenantId, string $environmentId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);
        $this->authorize('viewAny', [TaskRun::class, $tenant]);

        $validated = $request->validate([
            'type' => ['sometimes', 'nullable', 'string', 'max:128'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $runs = $this->dlq->list(
            $environment->public_id,
            isset($validated['type']) && is_string($validated['type']) ? $validated['type'] : null,
            (int) ($validated['limit'] ?? 50),
        )->filter(fn (TaskRun $run) => $run->tenant_id === $tenant->id);

        return response()->json([
            'data' => TaskRunResource::collection($runs->values()),
        ]);
    }

    public function show(Request $request, string $tenantId, string $environmentId, string $runId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);
        $run = $this->resolveDeadRun($tenant, $environment, $runId);
        $this->authorize('view', [$run, $tenant]);

        return response()->json(['data' => new TaskRunResource($run)]);
    }

    public function replay(Request $request, string $tenantId, string $environmentId, string $runId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);
        $run = $this->resolveDeadRun($tenant, $environment, $runId);
        $this->authorize('retry', [$run, $tenant]);

        try {
            $newRun = $this->dlq->replay($run);
        } catch (InvalidStateTransitionException $exception) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_state',
                    'message' => $exception->getMessage(),
                ],
            ], 409);
        }

        return response()->json(['data' => new TaskRunResource($newRun->load(['task', 'environment']))], 202);
    }

    private function resolveDeadRun(Tenant $tenant, Environment $environment, string $runId): TaskRun
    {
        /** @var TaskRun $run */
        $run = TaskRun::query()
            ->where('public_id', $runId)
            ->where('tenant_id', $tenant->id)
            ->where('environment_id', $environment->id)
            ->where('run_state', 'dead')
            ->with(['task', 'environment', 'attempts'])
            ->firstOrFail();

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
