<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Tasks\RunLifecycleService;
use App\Domain\Execution\Enums\RunState;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskRunAttemptResource;
use App\Http\Resources\TaskRunResource;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskRunController extends Controller
{
    public function __construct(private readonly RunLifecycleService $runLifecycle) {}

    public function index(Request $request, string $tenantId, string $environmentId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);
        $this->authorize('viewAny', [TaskRun::class, $tenant]);

        $limit = min(100, max(1, (int) $request->query('limit', 50)));
        $before = $request->query('before');
        $taskPublicId = $request->query('task_id');
        $runState = $request->query('run_state');

        $query = TaskRun::query()
            ->where('task_runs.tenant_id', $tenant->id)
            ->where('task_runs.environment_id', $environment->id)
            ->orderByDesc('task_runs.created_at')
            ->orderByDesc('task_runs.id')
            ->with(['task', 'environment']);

        if (is_string($taskPublicId) && $taskPublicId !== '') {
            $query->whereHas('task', function ($builder) use ($taskPublicId): void {
                $builder->where('public_id', $taskPublicId);
            });
        }

        if (is_string($runState) && $runState !== '') {
            $request->validate([
                'run_state' => ['required', Rule::in(array_column(RunState::cases(), 'value'))],
            ]);
            $query->where('task_runs.run_state', $runState);
        }

        if (is_string($before) && $before !== '') {
            $beforeCursor = TaskRun::query()
                ->where('public_id', $before)
                ->where('tenant_id', $tenant->id)
                ->where('environment_id', $environment->id)
                ->first();

            if ($beforeCursor !== null) {
                $createdAt = $beforeCursor->created_at?->format('Y-m-d H:i:s');
                $query->where(function ($builder) use ($createdAt, $beforeCursor): void {
                    $builder->where('task_runs.created_at', '<', $createdAt)
                        ->orWhere(function ($inner) use ($createdAt, $beforeCursor): void {
                            $inner->where('task_runs.created_at', '=', $createdAt)
                                ->where('task_runs.id', '<', $beforeCursor->id);
                        });
                });
            } else {
                // ISO timestamp cursor (SQLite-friendly fallback)
                $normalized = preg_replace('/Z$/', '', str_replace('T', ' ', $before)) ?? $before;
                $query->where('task_runs.created_at', '<', $normalized);
            }
        }

        $page = $query->limit($limit + 1)->get();
        $hasMore = $page->count() > $limit;
        $runs = $page->take($limit)->values();

        $oldest = $runs->last();
        $nextBefore = null;
        if ($hasMore && $oldest?->created_at !== null) {
            $nextBefore = $oldest->created_at->utc()->format('Y-m-d\TH:i:s\Z');
        }

        return response()->json([
            'data' => TaskRunResource::collection($runs),
            'meta' => [
                'limit' => $limit,
                'next_before' => $nextBefore,
            ],
        ]);
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

        return response()->json(['data' => new TaskRunResource($run->fresh(['task', 'attempts', 'environment']))], 202);
    }

    private function resolveRun(Request $request): TaskRun
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);

        $run = TaskRun::query()
            ->where('public_id', $request->route('runId'))
            ->where('tenant_id', $tenant->id)
            ->where('environment_id', $environment->id)
            ->with(['task', 'attempts', 'environment'])
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
