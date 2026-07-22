<?php

namespace App\Application\Tasks;

use App\Application\Audit\AuditLogger;
use App\Domain\Execution\Enums\AttemptState;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Enums\TriggerType;
use App\Domain\Execution\IdempotencyKeyGenerator;
use App\Domain\Execution\InvalidStateTransitionException;
use App\Domain\Execution\OccurrenceKeyGenerator;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dead-letter queue over run_state=dead (R6). No separate DLQ table for P0.
 */
final class DlqService
{
    public function __construct(
        private readonly IdempotencyKeyGenerator $idempotencyKeyGenerator,
        private readonly OccurrenceKeyGenerator $occurrenceKeyGenerator,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @return Collection<int, TaskRun>
     */
    public function list(?string $workspacePublicId = null, ?string $taskType = null, int $limit = 50): Collection
    {
        $limit = max(1, min(500, $limit));

        return $this->baseQuery($workspacePublicId, $taskType)
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function find(string $publicId): ?TaskRun
    {
        return TaskRun::query()
            ->where('public_id', $publicId)
            ->where('run_state', RunState::Dead)
            ->with([
                'task',
                'environment',
                'attempts' => static fn ($q) => $q->orderBy('attempt_number'),
            ])
            ->first();
    }

    /**
     * Replay creates a fresh Pending run with a new delivery Idempotency-Key group.
     * The original dead run is left intact for history / retention.
     */
    public function replay(TaskRun $dead): TaskRun
    {
        if ($dead->run_state !== RunState::Dead) {
            throw new InvalidStateTransitionException('task_run', $dead->run_state->value, RunState::Pending->value);
        }

        $task = $dead->task;
        if ($task === null) {
            throw new InvalidStateTransitionException('task_run', $dead->run_state->value, RunState::Pending->value);
        }

        $idempotencyKey = $this->idempotencyKeyGenerator->forDlqReplay();
        $occurrenceKey = $this->occurrenceKeyGenerator->forDlqReplay($idempotencyKey);

        $newRun = DB::transaction(function () use ($dead, $task, $idempotencyKey, $occurrenceKey): TaskRun {
            $run = TaskRun::query()->create([
                'tenant_id' => $task->tenant_id,
                'environment_id' => $task->environment_id,
                'task_id' => $task->id,
                'trigger_type' => TriggerType::Manual,
                'scheduled_for' => null,
                'occurrence_key' => $occurrenceKey,
                'idempotency_key' => $idempotencyKey,
                'run_state' => RunState::Pending,
                'attempt_count' => 1,
            ]);

            TaskRunAttempt::query()->create([
                'tenant_id' => $task->tenant_id,
                'environment_id' => $task->environment_id,
                'task_run_id' => $run->id,
                'attempt_number' => 1,
                'attempt_state' => AttemptState::Pending,
            ]);

            return $run;
        });

        $this->auditLogger->log(
            action: 'dlq.replayed',
            resourceType: 'task_run',
            resourceId: $dead->public_id,
            tenantId: $dead->tenant_id,
            environmentId: $dead->environment_id,
            summary: [
                'source_run_id' => $dead->public_id,
                'new_run_id' => $newRun->public_id,
                'task_id' => $task->public_id,
                'task_type' => $task->task_type,
                'previous_idempotency_key' => $dead->idempotency_key,
                'new_idempotency_key' => $idempotencyKey,
            ],
        );

        return $newRun->fresh(['attempts', 'task']);
    }

    /**
     * @return list<TaskRun>
     */
    public function replayByType(string $taskType, ?string $workspacePublicId = null, int $limit = 50): array
    {
        $deadRuns = $this->list($workspacePublicId, $taskType, $limit);
        $replayed = [];

        foreach ($deadRuns as $dead) {
            $replayed[] = $this->replay($dead);
        }

        return $replayed;
    }

    /**
     * @return Builder<TaskRun>
     */
    private function baseQuery(?string $workspacePublicId, ?string $taskType): Builder
    {
        $query = TaskRun::query()
            ->where('run_state', RunState::Dead)
            ->with(['task', 'environment']);

        if ($workspacePublicId !== null && $workspacePublicId !== '') {
            $environmentId = Environment::query()
                ->where('public_id', $workspacePublicId)
                ->value('id');

            if ($environmentId === null) {
                return TaskRun::query()->whereRaw('1 = 0');
            }

            $query->where('environment_id', $environmentId);
        }

        if ($taskType !== null && $taskType !== '') {
            $query->whereHas('task', static function (Builder $builder) use ($taskType): void {
                $builder->where('task_type', $taskType);
            });
        }

        return $query;
    }
}
