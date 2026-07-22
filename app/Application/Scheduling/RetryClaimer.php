<?php

namespace App\Application\Scheduling;

use App\Domain\Execution\AttemptStateMachine;
use App\Domain\Execution\Enums\AttemptState;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\RunStateMachine;
use App\Domain\Scheduling\TaskTypeCatalog;
use App\Domain\Scheduling\WorkspaceFairnessInterleaver;
use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RetryClaimer
{
    public function __construct(
        private readonly Clock $clock,
        private readonly RunStateMachine $runStateMachine,
        private readonly AttemptStateMachine $attemptStateMachine,
        private readonly TaskTypeCatalog $taskTypeCatalog,
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

            $candidateLimit = max($batchSize * 5, 50);
            $runs = $this->fairness->interleaveByEnvironmentId(
                $this->selectRetryableRuns($candidateLimit, $now),
            );

            foreach ($runs as $run) {
                if (count($claimed) >= $batchSize) {
                    break;
                }

                $task = $run->task;
                if ($task === null || ! $capacity->canAccept($task)) {
                    continue;
                }

                $attempt = $this->tryClaimRetry($run, $now);

                if ($attempt !== null) {
                    $capacity->reserve($task);
                    $claimed[] = new ClaimedAttempt($run->fresh(['task']), $attempt);
                }
            }
        });

        return $claimed;
    }

    /**
     * @return list<TaskRun>
     */
    private function selectRetryableRuns(int $batchSize, DateTimeImmutable $now): array
    {
        $driver = DB::connection()->getDriverName();
        $nowString = $now->format('Y-m-d H:i:s');

        $query = TaskRun::query()
            ->where('run_state', RunState::RetryWait)
            ->whereNotNull('next_attempt_at')
            ->where('next_attempt_at', '<=', $nowString)
            ->orderBy('next_attempt_at')
            ->limit($batchSize);

        if ($driver === 'mysql') {
            $query->lock('FOR UPDATE SKIP LOCKED');
        } else {
            $query->lockForUpdate();
        }

        return $query->with('task')->get()->all();
    }

    private function tryClaimRetry(TaskRun $run, DateTimeImmutable $now): ?TaskRunAttempt
    {
        $nextAttemptNumber = $run->attempt_count + 1;
        $claimToken = (string) Str::uuid();
        $claimExpiresAt = $now->modify(sprintf('+%d minutes', (int) config('scheduler.claim_ttl_minutes', 10)));
        $nowString = $now->format('Y-m-d H:i:s');

        $this->runStateMachine->assertCanTransition($run->run_state, RunState::Running);

        $attempt = TaskRunAttempt::query()->create([
            'tenant_id' => $run->tenant_id,
            'environment_id' => $run->environment_id,
            'task_run_id' => $run->id,
            'attempt_number' => $nextAttemptNumber,
            'attempt_state' => AttemptState::Pending,
            'claim_token' => $claimToken,
            'claimed_at' => $now,
            'claim_expires_at' => $claimExpiresAt,
        ]);

        $run->attempt_count = $nextAttemptNumber;
        $run->next_attempt_at = null;
        $run->save();

        return $attempt;
    }
}
