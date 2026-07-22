<?php

namespace App\Application\Scheduling;

use App\Domain\Execution\Enums\AttemptState;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Scheduling\TaskTypeCatalog;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use Illuminate\Support\Facades\DB;

/**
 * Tracks in-flight capacity by task type + global ceiling (R4).
 * Weight units consume capacity; claiming stops when remaining capacity is 0.
 */
final class InFlightCapacityTracker
{
    /** @var array<string, int> */
    private array $typeWeight = [];

    private int $globalWeight = 0;

    public function __construct(
        private readonly TaskTypeCatalog $catalog,
    ) {
    }

    public function refreshFromDatabase(): void
    {
        $this->typeWeight = [];
        $this->globalWeight = 0;

        // Pending = claimed this tick but not yet delivering; Running = actively delivering.
        $openRunTaskIds = TaskRun::query()
            ->whereIn('run_state', [RunState::Pending, RunState::Running])
            ->pluck('task_id')
            ->all();

        $claimedAttemptTaskIds = DB::table('task_run_attempts')
            ->join('task_runs', 'task_runs.id', '=', 'task_run_attempts.task_run_id')
            ->whereIn('task_run_attempts.attempt_state', [
                AttemptState::Pending->value,
                AttemptState::Running->value,
            ])
            ->whereNotNull('task_run_attempts.claim_token')
            ->where('task_run_attempts.claim_expires_at', '>', now())
            ->pluck('task_runs.task_id')
            ->all();

        $taskIds = array_values(array_unique(array_merge($openRunTaskIds, $claimedAttemptTaskIds)));

        if ($taskIds === []) {
            return;
        }

        foreach (Task::query()->whereIn('id', $taskIds)->get(['id', 'task_type', 'weight']) as $task) {
            $this->reserve($task);
        }
    }

    public function canAccept(Task $task): bool
    {
        $weight = $this->weightFor($task);
        $type = $this->typeKey($task);
        $def = $this->catalog->definition($task->task_type);
        $typeUsed = $this->typeWeight[$type] ?? 0;

        if ($typeUsed + $weight > $def['concurrency_cap']) {
            return false;
        }

        if ($this->globalWeight + $weight > $this->catalog->globalCeiling()) {
            return false;
        }

        return true;
    }

    public function reserve(Task $task): void
    {
        $weight = $this->weightFor($task);
        $type = $this->typeKey($task);
        $this->typeWeight[$type] = ($this->typeWeight[$type] ?? 0) + $weight;
        $this->globalWeight += $weight;
    }

    public function remainingGlobal(): int
    {
        return max(0, $this->catalog->globalCeiling() - $this->globalWeight);
    }

    private function weightFor(Task $task): int
    {
        if ($task->weight !== null) {
            return max(1, (int) $task->weight);
        }

        return $this->catalog->definition($task->task_type)['weight'];
    }

    private function typeKey(Task $task): string
    {
        $type = $task->task_type;

        return $type !== null && $type !== '' ? $type : 'default';
    }
}
