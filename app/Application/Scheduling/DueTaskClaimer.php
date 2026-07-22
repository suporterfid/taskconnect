<?php

namespace App\Application\Scheduling;

use App\Domain\Execution\Enums\AttemptState;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Enums\TaskDefinitionStatus;
use App\Domain\Execution\Enums\TriggerType;
use App\Domain\Execution\IdempotencyKeyGenerator;
use App\Domain\Execution\OccurrenceKeyGenerator;
use App\Domain\Scheduling\ScheduleCalculator;
use App\Domain\Scheduling\ScheduleKind;
use App\Domain\Scheduling\TaskTypeCatalog;
use App\Domain\Scheduling\WorkspaceFairnessInterleaver;
use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DueTaskClaimer
{
    public function __construct(
        private readonly Clock $clock,
        private readonly ScheduleCalculator $scheduleCalculator,
        private readonly IdempotencyKeyGenerator $idempotencyKeyGenerator,
        private readonly OccurrenceKeyGenerator $occurrenceKeyGenerator,
        private readonly TaskTypeCatalog $taskTypeCatalog,
        private readonly SchedulerAuditRecorder $auditRecorder,
        private readonly WorkspaceFairnessInterleaver $fairness = new WorkspaceFairnessInterleaver,
    ) {
    }

    /**
     * @return list<ClaimedAttempt>
     */
    public function claim(int $batchSize): array
    {
        $now = $this->clock->nowUtc();
        $claimed = [];

        DB::transaction(function () use ($batchSize, $now, &$claimed): void {
            $capacity = new InFlightCapacityTracker($this->taskTypeCatalog);
            $capacity->refreshFromDatabase();

            if ($capacity->remainingGlobal() <= 0) {
                return;
            }

            // Over-fetch so saturated high-priority types / workspaces do not hide other due work.
            $candidateLimit = max($batchSize * 20, 100);
            $tasks = $this->fairness->interleave($this->selectDueTasks($candidateLimit, $now));

            foreach ($tasks as $task) {
                if (count($claimed) >= $batchSize) {
                    break;
                }

                if (! $capacity->canAccept($task)) {
                    continue;
                }

                $attempt = $this->tryClaimTask($task, $now);

                if ($attempt !== null) {
                    $capacity->reserve($task);
                    $claimed[] = $attempt;
                }
            }
        });

        foreach ($claimed as $claimedAttempt) {
            $this->auditRecorder->recordClaim($claimedAttempt, 'due');
        }

        return $claimed;
    }

    /**
     * @return list<Task>
     */
    private function selectDueTasks(int $batchSize, DateTimeImmutable $now): array
    {
        $driver = DB::connection()->getDriverName();
        $nowString = $now->format('Y-m-d H:i:s');

        $query = Task::query()
            ->where('definition_status', TaskDefinitionStatus::Active)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $nowString)
            ->where(function ($builder) use ($nowString): void {
                $builder->whereNull('claim_token')
                    ->orWhere('claim_expires_at', '<', $nowString);
            })
            ->orderByDesc('priority')
            ->orderBy('next_run_at')
            ->limit($batchSize);

        if ($driver === 'mysql') {
            $query->lock('FOR UPDATE SKIP LOCKED');
        } else {
            $query->lockForUpdate();
        }

        return $query->with('schedule')->get()->all();
    }

    private function tryClaimTask(Task $task, DateTimeImmutable $now): ?ClaimedAttempt
    {
        if ($task->next_run_at === null) {
            return null;
        }

        $claimToken = (string) Str::uuid();
        $claimExpiresAt = $now->modify(sprintf('+%d minutes', (int) config('scheduler.claim_ttl_minutes', 10)));
        $nowString = $now->format('Y-m-d H:i:s');

        $updated = Task::query()
            ->where('id', $task->id)
            ->where('definition_status', TaskDefinitionStatus::Active)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $nowString)
            ->where(function ($builder) use ($nowString): void {
                $builder->whereNull('claim_token')
                    ->orWhere('claim_expires_at', '<', $nowString);
            })
            ->update([
                'claim_token' => $claimToken,
                'claimed_at' => $nowString,
                'claim_expires_at' => $claimExpiresAt->format('Y-m-d H:i:s'),
            ]);

        if ($updated === 0) {
            return null;
        }

        $task->refresh();
        $scheduledFor = DateTimeImmutable::createFromInterface($task->next_run_at);
        $occurrenceKey = $this->occurrenceKeyGenerator->forScheduled($scheduledFor);
        $idempotencyKey = $this->idempotencyKeyGenerator->forScheduledRun($task->id, $scheduledFor);

        try {
            $run = TaskRun::query()->create([
                'tenant_id' => $task->tenant_id,
                'environment_id' => $task->environment_id,
                'task_id' => $task->id,
                'trigger_type' => TriggerType::Scheduled,
                'scheduled_for' => $scheduledFor,
                'occurrence_key' => $occurrenceKey,
                'idempotency_key' => $idempotencyKey,
                'run_state' => RunState::Pending,
                'attempt_count' => 1,
            ]);

            $attempt = TaskRunAttempt::query()->create([
                'tenant_id' => $task->tenant_id,
                'environment_id' => $task->environment_id,
                'task_run_id' => $run->id,
                'attempt_number' => 1,
                'attempt_state' => AttemptState::Pending,
            ]);
        } catch (QueryException) {
            $this->releaseTaskClaim($task);

            return null;
        }

        $this->advanceNextRunAt($task, $now);
        $this->releaseTaskClaim($task);

        return new ClaimedAttempt($run->fresh(['task']), $attempt->fresh());
    }

    private function advanceNextRunAt(Task $task, DateTimeImmutable $now): void
    {
        $schedule = $task->schedule;

        if ($schedule === null) {
            $task->next_run_at = null;
            $task->save();

            return;
        }

        $config = $schedule->toScheduleConfig();

        if ($config->kind === ScheduleKind::Once) {
            $task->definition_status = TaskDefinitionStatus::Completed;
            $task->next_run_at = null;
        } else {
            $task->next_run_at = $this->scheduleCalculator->nextRunAt($config, $now);
        }

        $schedule->last_calculated_at = $now;
        $schedule->save();
        $task->save();
    }

    private function releaseTaskClaim(Task $task): void
    {
        $task->claim_token = null;
        $task->claimed_at = null;
        $task->claim_expires_at = null;
        $task->save();
    }
}
