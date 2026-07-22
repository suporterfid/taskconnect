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
 *
 * Per-type caps are concurrency counts (number of open jobs of that type).
 * Global ceiling is consumed in weight units so a heavy job costs more shared capacity.
 */
final class InFlightCapacityTracker
{
    /** @var array<string, int> */
    private array $typeCount = [];

    private int $globalWeight = 0;

    public function __construct(
        private readonly TaskTypeCatalog $catalog,
    ) {
    }

    public function refreshFromDatabase(): void
    {
        $this->typeCount = [];
        $this->globalWeight = 0;

        // Each open run consumes capacity (do not collapse by task_id).
        $openRuns = TaskRun::query()
            ->whereIn('run_state', [RunState::Pending, RunState::Running])
            ->get(['id', 'task_id']);

        $claimedAttemptRunIds = DB::table('task_run_attempts')
            ->whereIn('attempt_state', [
                AttemptState::Pending->value,
                AttemptState::Running->value,
            ])
            ->whereNotNull('claim_token')
            ->where('claim_expires_at', '>', now())
            ->pluck('task_run_id')
            ->all();

        $extraRuns = $claimedAttemptRunIds === []
            ? collect()
            : TaskRun::query()
                ->whereIn('id', $claimedAttemptRunIds)
                ->whereNotIn('run_state', [RunState::Pending, RunState::Running])
                ->get(['id', 'task_id']);

        $runs = $openRuns->concat($extraRuns);
        if ($runs->isEmpty()) {
            return;
        }

        $tasks = Task::query()
            ->whereIn('id', $runs->pluck('task_id')->unique()->all())
            ->get(['id', 'task_type', 'weight'])
            ->keyBy('id');

        foreach ($runs as $run) {
            $task = $tasks->get($run->task_id);
            if ($task !== null) {
                $this->reserve($task);
            }
        }
    }

    public function canAccept(Task $task): bool
    {
        $weight = $this->weightFor($task);
        $type = $this->typeKey($task);
        $def = $this->catalog->definition($task->task_type);
        $typeUsed = $this->typeCount[$type] ?? 0;

        if ($typeUsed + 1 > $def['concurrency_cap']) {
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
        $this->typeCount[$type] = ($this->typeCount[$type] ?? 0) + 1;
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
