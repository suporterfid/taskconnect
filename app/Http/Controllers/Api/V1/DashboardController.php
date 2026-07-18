<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\SystemHeartbeat;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $environment = $request->attributes->get('environment');

        if ($tenant === null || $environment === null) {
            abort(404);
        }

        $taskQuery = Task::query()
            ->where('tenant_id', $tenant->id)
            ->where('environment_id', $environment->id)
            ->whereNull('archived_at');

        $runQuery = TaskRun::query()
            ->where('tenant_id', $tenant->id)
            ->where('environment_id', $environment->id);

        $heartbeat = SystemHeartbeat::query()->where('name', 'scheduler.execute_due')->first();

        $oldestDue = (clone $taskQuery)
            ->where('definition_status', 'active')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->value('next_run_at');

        return response()->json([
            'data' => [
                'active_tasks' => (clone $taskQuery)->where('definition_status', 'active')->count(),
                'paused_tasks' => (clone $taskQuery)->where('definition_status', 'paused')->count(),
                'recent_runs' => (clone $runQuery)->where('created_at', '>=', now()->subDay())->count(),
                'failed_runs_24h' => (clone $runQuery)
                    ->where('run_state', 'dead')
                    ->where('updated_at', '>=', now()->subDay())
                    ->count(),
                'retry_wait_runs' => (clone $runQuery)->where('run_state', 'retry_wait')->count(),
                'dead_runs' => (clone $runQuery)->where('run_state', 'dead')->count(),
                'upcoming_tasks' => (clone $taskQuery)
                    ->where('definition_status', 'active')
                    ->whereNotNull('next_run_at')
                    ->orderBy('next_run_at')
                    ->limit(10)
                    ->get(['public_id', 'name', 'next_run_at'])
                    ->map(fn (Task $task) => [
                        'id' => $task->public_id,
                        'name' => $task->name,
                        'next_run_at' => $task->next_run_at?->utc()->format('Y-m-d\TH:i:s\Z'),
                    ])
                    ->values()
                    ->all(),
                'oldest_due_at' => $oldestDue === null
                    ? null
                    : Carbon::parse($oldestDue)->utc()->format('Y-m-d\TH:i:s\Z'),
                'scheduler_last_seen_at' => $heartbeat?->last_seen_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            ],
        ]);
    }
}
