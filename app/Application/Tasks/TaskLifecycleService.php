<?php

namespace App\Application\Tasks;

use App\Domain\Execution\Enums\AttemptState;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Enums\TaskDefinitionStatus;
use App\Domain\Execution\Enums\TriggerType;
use App\Domain\Execution\IdempotencyKeyGenerator;
use App\Domain\Execution\InvalidStateTransitionException;
use App\Domain\Execution\OccurrenceKeyGenerator;
use App\Domain\Execution\RetryPolicy;
use App\Domain\Scheduling\ScheduleConfig;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class TaskLifecycleService
{
    public function __construct(
        private readonly TaskScheduleService $scheduleService,
        private readonly IdempotencyKeyGenerator $idempotencyKeyGenerator,
        private readonly OccurrenceKeyGenerator $occurrenceKeyGenerator,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ScheduleConfig $schedule, ?int $userId = null): Task
    {
        return DB::transaction(function () use ($attributes, $schedule, $userId): Task {
            /** @var Task $task */
            $task = Task::query()->create(array_merge($attributes, [
                'definition_status' => $attributes['definition_status'] ?? TaskDefinitionStatus::Draft,
                'retry_policy_json' => ($attributes['retry_policy_json'] ?? RetryPolicy::default()->toArray()),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));

            $this->scheduleService->upsertSchedule($task, $schedule);

            if ($task->definition_status === TaskDefinitionStatus::Active) {
                $this->scheduleService->syncNextRunAt($task->fresh(['schedule']));
            }

            return $task->fresh(['schedule']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Task $task, array $attributes, ?ScheduleConfig $schedule = null, ?int $userId = null): Task
    {
        return DB::transaction(function () use ($task, $attributes, $schedule, $userId): Task {
            if ($userId !== null) {
                $attributes['updated_by'] = $userId;
            }

            $task->fill($attributes);
            $task->save();

            if ($schedule !== null) {
                $this->scheduleService->upsertSchedule($task, $schedule);
            }

            if ($task->definition_status === TaskDefinitionStatus::Active) {
                $this->scheduleService->syncNextRunAt($task->fresh(['schedule']));
            }

            return $task->fresh(['schedule']);
        });
    }

    public function activate(Task $task): Task
    {
        $this->transitionDefinitionStatus($task, TaskDefinitionStatus::Active);
        $this->scheduleService->syncNextRunAt($task->fresh(['schedule']));

        return $task->fresh(['schedule']);
    }

    public function pause(Task $task): Task
    {
        $this->transitionDefinitionStatus($task, TaskDefinitionStatus::Paused);

        return $task->fresh(['schedule']);
    }

    public function resume(Task $task): Task
    {
        $this->transitionDefinitionStatus($task, TaskDefinitionStatus::Active);
        $this->scheduleService->syncNextRunAt($task->fresh(['schedule']));

        return $task->fresh(['schedule']);
    }

    public function archive(Task $task): Task
    {
        $this->transitionDefinitionStatus($task, TaskDefinitionStatus::Archived);
        $task->archive();

        return $task->fresh(['schedule']);
    }

    public function duplicate(Task $task, ?int $userId = null): Task
    {
        $copy = $task->replicate(['public_id', 'next_run_at', 'last_run_at', 'last_run_state', 'claim_token', 'claimed_at', 'claim_expires_at']);
        $copy->name = $task->name.' (copy)';
        $copy->definition_status = TaskDefinitionStatus::Draft;
        $copy->created_by = $userId;
        $copy->updated_by = $userId;
        $copy->save();

        if ($task->schedule !== null) {
            $scheduleCopy = $task->schedule->replicate(['public_id', 'task_id']);
            $scheduleCopy->task_id = $copy->id;
            $scheduleCopy->save();
        }

        return $copy->fresh(['schedule']);
    }

    public function queueManualRun(Task $task, ?string $clientIdempotencyKey = null): TaskRun
    {
        $originalNextRunAt = $task->next_run_at;
        $idempotencyKey = $this->idempotencyKeyGenerator->forManualRun($clientIdempotencyKey);
        $occurrenceKey = $this->occurrenceKeyGenerator->forManual($idempotencyKey);

        try {
            $run = DB::transaction(function () use ($task, $idempotencyKey, $occurrenceKey): TaskRun {
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
        } catch (QueryException $exception) {
            if ($clientIdempotencyKey !== null) {
                $existing = TaskRun::query()
                    ->where('task_id', $task->id)
                    ->where('occurrence_key', $occurrenceKey)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $exception;
        }

        $task->refresh();
        if ($task->next_run_at != $originalNextRunAt) {
            $task->next_run_at = $originalNextRunAt;
            $task->save();
        }

        return $run->fresh(['attempts']);
    }

    public function queueTestRun(Task $task): TaskRun
    {
        $originalNextRunAt = $task->next_run_at;
        $testToken = $this->occurrenceKeyGenerator->forTest();
        $idempotencyKey = $this->idempotencyKeyGenerator->forTestRun();

        $run = DB::transaction(function () use ($task, $testToken, $idempotencyKey): TaskRun {
            $run = TaskRun::query()->create([
                'tenant_id' => $task->tenant_id,
                'environment_id' => $task->environment_id,
                'task_id' => $task->id,
                'trigger_type' => TriggerType::Test,
                'scheduled_for' => null,
                'occurrence_key' => $testToken,
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

        $task->refresh();
        if ($task->next_run_at != $originalNextRunAt) {
            $task->next_run_at = $originalNextRunAt;
            $task->save();
        }

        return $run->fresh(['attempts']);
    }

    private function transitionDefinitionStatus(Task $task, TaskDefinitionStatus $target): void
    {
        if (! $task->definition_status->canTransitionTo($target)) {
            throw new InvalidStateTransitionException(
                'task_definition',
                $task->definition_status->value,
                $target->value,
            );
        }

        $task->definition_status = $target;
        $task->save();
    }
}
