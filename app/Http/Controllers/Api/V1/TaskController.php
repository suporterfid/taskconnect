<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Audit\AuditLogger;
use App\Application\Tasks\TaskLifecycleService;
use App\Application\Tenancy\EnvironmentGuard;
use App\Domain\Execution\Enums\TaskDefinitionStatus;
use App\Domain\Scheduling\ScheduleConfig;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TaskRunResource;
use App\Infrastructure\Persistence\Eloquent\EndpointProfile;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskLifecycleService $lifecycle,
        private readonly AuditLogger $auditLogger,
        private readonly EnvironmentGuard $environmentGuard,
    ) {}

    public function index(Request $request, string $tenantId, string $environmentId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);
        $this->authorize('viewAny', [Task::class, $tenant]);

        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'definition_status' => ['sometimes', 'nullable', Rule::in(array_column(TaskDefinitionStatus::cases(), 'value'))],
            'last_run_state' => ['sometimes', 'nullable', 'string', 'max:64'],
            'schedule_kind' => ['sometimes', 'nullable', 'string', 'max:64'],
            'sort' => ['sometimes', 'nullable', Rule::in(['name', 'next_run_at', 'last_run_at'])],
            'order' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
        ]);

        $query = Task::query()
            ->where('tasks.tenant_id', $tenant->id)
            ->where('tasks.environment_id', $environment->id)
            ->whereNull('tasks.archived_at')
            ->with(['schedule', 'endpointProfile']);

        if (! empty($validated['q'])) {
            $term = '%'.$validated['q'].'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('tasks.name', 'like', $term)
                    ->orWhere('tasks.description', 'like', $term);
            });
        }

        if (! empty($validated['definition_status'])) {
            $query->where('tasks.definition_status', $validated['definition_status']);
        }

        if (! empty($validated['last_run_state'])) {
            $query->where('tasks.last_run_state', $validated['last_run_state']);
        }

        if (! empty($validated['schedule_kind'])) {
            $query->whereHas('schedule', function ($builder) use ($validated): void {
                $builder->where('schedule_kind', $validated['schedule_kind']);
            });
        }

        $sort = $validated['sort'] ?? 'name';
        $order = $validated['order'] ?? 'asc';
        $query->orderBy('tasks.'.$sort, $order)->orderBy('tasks.id');

        $tasks = $query->get();

        return response()->json(['data' => TaskResource::collection($tasks)]);
    }

    public function store(Request $request, string $tenantId, string $environmentId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);
        $this->authorize('create', [Task::class, $tenant]);
        $this->environmentGuard->assertActive($environment);

        $validated = $this->validateTaskPayload($request, $tenant, $environment);
        $schedule = ScheduleConfig::fromArray($validated['schedule']);

        $task = $this->lifecycle->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'endpoint_profile_id' => $this->resolveEndpointProfileId($validated['endpoint_profile_id'] ?? null, $tenant, $environment),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'definition_status' => TaskDefinitionStatus::from($validated['definition_status'] ?? TaskDefinitionStatus::Draft->value),
            'method' => strtoupper($validated['method']),
            'url_or_path' => $validated['url_or_path'] ?? $validated['path'] ?? $validated['url'],
            'headers_json' => $validated['headers'] ?? [],
            'query_json' => $validated['query'] ?? [],
            'body_template' => isset($validated['body']) ? (is_string($validated['body']) ? $validated['body'] : json_encode($validated['body'])) : null,
            'content_type' => $validated['content_type'] ?? null,
            'timezone' => $validated['schedule']['timezone'],
            'retry_policy_json' => $validated['retry_policy'] ?? null,
        ], $schedule, $request->user()?->id);

        $this->audit($request, $tenant, 'task.created', $task->public_id);

        return response()->json(['data' => new TaskResource($task)], 201);
    }

    public function show(Request $request, string $tenantId, string $environmentId, string $taskId): JsonResponse
    {
        $task = $this->resolveTask($request);

        return response()->json(['data' => new TaskResource($task)]);
    }

    public function update(Request $request, string $tenantId, string $environmentId, string $taskId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);
        $task = $this->resolveTask($request);
        $this->authorize('update', [$task, $tenant]);

        $validated = $this->validateTaskPayload($request, $tenant, $environment, partial: true);
        $schedule = isset($validated['schedule']) ? ScheduleConfig::fromArray($validated['schedule']) : null;

        $attributes = array_filter([
            'endpoint_profile_id' => array_key_exists('endpoint_profile_id', $validated)
                ? $this->resolveEndpointProfileId($validated['endpoint_profile_id'], $tenant, $environment)
                : null,
            'name' => $validated['name'] ?? null,
            'description' => $validated['description'] ?? null,
            'method' => isset($validated['method']) ? strtoupper($validated['method']) : null,
            'url_or_path' => $validated['url_or_path'] ?? $validated['path'] ?? $validated['url'] ?? null,
            'headers_json' => $validated['headers'] ?? null,
            'query_json' => $validated['query'] ?? null,
            'body_template' => isset($validated['body']) ? (is_string($validated['body']) ? $validated['body'] : json_encode($validated['body'])) : null,
            'content_type' => $validated['content_type'] ?? null,
            'timezone' => $validated['schedule']['timezone'] ?? null,
            'retry_policy_json' => $validated['retry_policy'] ?? null,
            'definition_status' => isset($validated['definition_status'])
                ? TaskDefinitionStatus::from($validated['definition_status'])
                : null,
        ], fn ($value) => $value !== null);

        $task = $this->lifecycle->update($task, $attributes, $schedule, $request->user()?->id);
        $this->audit($request, $tenant, 'task.updated', $task->public_id);

        return response()->json(['data' => new TaskResource($task)]);
    }

    public function destroy(Request $request, string $tenantId, string $environmentId, string $taskId): Response
    {
        $tenant = $this->tenant($request);
        $task = $this->resolveTask($request);
        $this->authorize('delete', [$task, $tenant]);

        $this->lifecycle->archive($task);
        $this->audit($request, $tenant, 'task.archived', $task->public_id);

        return response()->noContent();
    }

    public function activate(Request $request, string $tenantId, string $environmentId, string $taskId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);
        $task = $this->resolveTask($request);
        $this->authorize('operate', [$task, $tenant]);
        $this->environmentGuard->assertActive($environment);

        $task = $this->lifecycle->activate($task);
        $this->audit($request, $tenant, 'task.activated', $task->public_id);

        return response()->json(['data' => new TaskResource($task)]);
    }

    public function pause(Request $request, string $tenantId, string $environmentId, string $taskId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $task = $this->resolveTask($request);
        $this->authorize('operate', [$task, $tenant]);

        $task = $this->lifecycle->pause($task);
        $this->audit($request, $tenant, 'task.paused', $task->public_id);

        return response()->json(['data' => new TaskResource($task)]);
    }

    public function resume(Request $request, string $tenantId, string $environmentId, string $taskId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);
        $task = $this->resolveTask($request);
        $this->authorize('operate', [$task, $tenant]);
        $this->environmentGuard->assertActive($environment);

        $task = $this->lifecycle->resume($task);
        $this->audit($request, $tenant, 'task.resumed', $task->public_id);

        return response()->json(['data' => new TaskResource($task)]);
    }

    public function bulkPause(Request $request, string $tenantId, string $environmentId): JsonResponse
    {
        return $this->bulkLifecycle($request, 'pause');
    }

    public function bulkResume(Request $request, string $tenantId, string $environmentId): JsonResponse
    {
        return $this->bulkLifecycle($request, 'resume');
    }

    public function runNow(Request $request, string $tenantId, string $environmentId, string $taskId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);
        $task = $this->resolveTask($request);
        $this->authorize('operate', [$task, $tenant]);
        $this->environmentGuard->assertActive($environment);

        $clientKey = $request->header('Idempotency-Key');
        $run = $this->lifecycle->queueManualRun($task, is_string($clientKey) ? $clientKey : null);
        $this->audit($request, $tenant, 'task.run_now', $task->public_id, ['run_id' => $run->public_id]);

        return response()->json(['data' => new TaskRunResource($run->load('task'))], 202);
    }

    public function test(Request $request, string $tenantId, string $environmentId, string $taskId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);
        $task = $this->resolveTask($request);
        $this->authorize('operate', [$task, $tenant]);
        $this->environmentGuard->assertActive($environment);

        $run = $this->lifecycle->queueTestRun($task);
        $this->audit($request, $tenant, 'task.test', $task->public_id, ['run_id' => $run->public_id]);

        return response()->json(['data' => new TaskRunResource($run->load('task'))], 202);
    }

    public function duplicate(Request $request, string $tenantId, string $environmentId, string $taskId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $task = $this->resolveTask($request);
        $this->authorize('create', [Task::class, $tenant]);

        $copy = $this->lifecycle->duplicate($task, $request->user()?->id);
        $this->audit($request, $tenant, 'task.duplicated', $copy->public_id, ['source_task_id' => $task->public_id]);

        return response()->json(['data' => new TaskResource($copy)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTaskPayload(Request $request, Tenant $tenant, Environment $environment, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'endpoint_profile_id' => ['nullable', 'string'],
            'method' => [$required, 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS', 'get', 'post', 'put', 'patch', 'delete', 'head', 'options'])],
            'url_or_path' => ['sometimes', 'string'],
            'path' => ['sometimes', 'string'],
            'url' => ['sometimes', 'string'],
            'headers' => ['nullable', 'array'],
            'query' => ['nullable', 'array'],
            'body' => ['nullable'],
            'content_type' => ['nullable', 'string'],
            'schedule' => [$required, 'array'],
            'retry_policy' => ['nullable', 'array'],
            'retry_policy.max_attempts' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'retry_policy.delay_seconds' => ['sometimes', 'array'],
            'retry_policy.delay_seconds.*' => ['integer', 'min:0'],
            'retry_policy.honor_retry_after' => ['sometimes', 'boolean'],
            'retry_policy.max_retry_window_seconds' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'retry_policy.retryable_status_codes' => ['sometimes', 'nullable', 'array'],
            'retry_policy.retryable_status_codes.*' => ['integer', 'min:100', 'max:599'],
            'retry_policy.success_status_ranges' => ['sometimes', 'nullable', 'array'],
            'retry_policy.success_status_ranges.*' => [
                'array',
                'size:2',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_array($value) || count($value) < 2) {
                        return;
                    }

                    $min = (int) $value[0];
                    $max = (int) $value[1];

                    if ($min < 100 || $max > 599) {
                        $fail('The '.$attribute.' values must be between 100 and 599.');
                    }

                    if ($min > $max) {
                        $fail('The '.$attribute.' min must be less than or equal to max.');
                    }
                },
            ],
            'retry_policy.success_status_ranges.*.*' => ['integer', 'min:100', 'max:599'],
            'definition_status' => ['nullable', Rule::in(array_column(TaskDefinitionStatus::cases(), 'value'))],
        ]);
    }

    private function resolveEndpointProfileId(?string $publicId, Tenant $tenant, Environment $environment): ?int
    {
        if ($publicId === null || $publicId === '') {
            return null;
        }

        return EndpointProfile::query()
            ->where('public_id', $publicId)
            ->where('tenant_id', $tenant->id)
            ->where('environment_id', $environment->id)
            ->value('id');
    }

    /**
     * @param  'pause'|'resume'  $action
     */
    private function bulkLifecycle(Request $request, string $action): JsonResponse
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);
        $this->authorize('viewAny', [Task::class, $tenant]);

        if ($action === 'resume') {
            $this->environmentGuard->assertActive($environment);
        }

        $validated = $request->validate([
            'task_ids' => ['required', 'array', 'min:1', 'max:100'],
            'task_ids.*' => ['required', 'string', 'max:64'],
        ]);

        $tasks = Task::query()
            ->where('tenant_id', $tenant->id)
            ->where('environment_id', $environment->id)
            ->whereNull('archived_at')
            ->whereIn('public_id', $validated['task_ids'])
            ->with(['schedule', 'endpointProfile'])
            ->get()
            ->keyBy('public_id');

        $updated = [];
        $skipped = [];

        foreach ($validated['task_ids'] as $publicId) {
            $task = $tasks->get($publicId);
            if ($task === null) {
                $skipped[] = ['id' => $publicId, 'reason' => 'not_found'];
                continue;
            }

            try {
                $this->authorize('operate', [$task, $tenant]);
                $task = $action === 'pause'
                    ? $this->lifecycle->pause($task)
                    : $this->lifecycle->resume($task);
                $this->audit(
                    $request,
                    $tenant,
                    $action === 'pause' ? 'task.paused' : 'task.resumed',
                    $task->public_id,
                    ['bulk' => true],
                );
                $updated[] = $task->public_id;
            } catch (\Throwable) {
                $skipped[] = ['id' => $publicId, 'reason' => 'not_operable'];
            }
        }

        return response()->json([
            'data' => [
                'action' => $action,
                'updated' => $updated,
                'skipped' => $skipped,
            ],
        ]);
    }

    private function resolveTask(Request $request): Task
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);

        $task = Task::query()
            ->where('public_id', $request->route('taskId'))
            ->where('tenant_id', $tenant->id)
            ->where('environment_id', $environment->id)
            ->with(['schedule', 'endpointProfile'])
            ->firstOrFail();

        $this->authorize('view', [$task, $tenant]);

        return $task;
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

    /**
     * @param  array<string, mixed>  $summary
     */
    private function audit(Request $request, Tenant $tenant, string $action, string $resourceId, array $summary = []): void
    {
        $this->auditLogger->logFromRequest(
            $request,
            action: $action,
            resourceType: 'task',
            resourceId: $resourceId,
            tenantId: $tenant->id,
            summary: $summary,
        );
    }
}
