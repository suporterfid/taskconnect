<?php

namespace App\Application\Scheduling;

use App\Domain\Execution\AttemptStateMachine;
use App\Domain\Execution\Enums\AttemptState;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Enums\TaskDefinitionStatus;
use App\Domain\Execution\RetryDecider;
use App\Domain\Execution\RunStateMachine;
use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use DateTimeImmutable;
use Illuminate\Support\Facades\Schema;

final class StaleClaimRecovery
{
    public function __construct(
        private readonly Clock $clock,
        private readonly RunStateMachine $runStateMachine,
        private readonly AttemptStateMachine $attemptStateMachine,
        private readonly RetryDecider $retryDecider,
    ) {
    }

    public function recover(int $batchSize = 50): int
    {
        if (! Schema::hasTable('tasks') || ! Schema::hasTable('task_run_attempts')) {
            return 0;
        }

        $now = $this->clock->nowUtc();
        $recovered = 0;

        $recovered += $this->recoverStaleTaskClaims($now, $batchSize);
        $recovered += $this->recoverStaleAttemptClaims($now, $batchSize);

        return $recovered;
    }

    private function recoverStaleTaskClaims(DateTimeImmutable $now, int $batchSize): int
    {
        $nowString = $now->format('Y-m-d H:i:s');
        $tasks = Task::query()
            ->whereNotNull('claim_token')
            ->where('claim_expires_at', '<', $nowString)
            ->limit($batchSize)
            ->get();

        $count = 0;

        foreach ($tasks as $task) {
            $task->claim_token = null;
            $task->claimed_at = null;
            $task->claim_expires_at = null;
            $task->save();
            $count++;
        }

        return $count;
    }

    private function recoverStaleAttemptClaims(DateTimeImmutable $now, int $batchSize): int
    {
        $nowString = $now->format('Y-m-d H:i:s');

        $attempts = TaskRunAttempt::query()
            ->where('attempt_state', AttemptState::Running)
            ->whereNotNull('claim_token')
            ->where('claim_expires_at', '<', $nowString)
            ->limit($batchSize)
            ->with('run.task')
            ->get();

        $count = 0;

        foreach ($attempts as $attempt) {
            if (! $this->attemptStateMachine->canTransition($attempt->attempt_state, AttemptState::Interrupted)) {
                continue;
            }

            $attempt->attempt_state = AttemptState::Interrupted;
            $attempt->claim_token = null;
            $attempt->claimed_at = null;
            $attempt->claim_expires_at = null;
            $attempt->finished_at = $now;
            $attempt->save();

            $run = $attempt->run;
            $task = $run->task;
            $policy = $task->retryPolicy();

            $runStartedAt = null;
            if ($run->started_at !== null) {
                $runStartedAt = DateTimeImmutable::createFromInterface($run->started_at);
            } elseif ($attempt->started_at !== null) {
                $runStartedAt = DateTimeImmutable::createFromInterface($attempt->started_at);
            }

            if ($this->retryDecider->shouldRetry(
                0,
                'interrupted',
                $attempt->attempt_number,
                $policy,
                $runStartedAt,
                $now,
            )
                && $this->runStateMachine->canTransition($run->run_state, RunState::RetryWait)) {
                $delay = $this->retryDecider->nextDelaySeconds($attempt->attempt_number, $policy);
                $nextAttemptAt = $now->modify(sprintf('+%d seconds', $delay));

                $run->run_state = RunState::RetryWait;
                $run->next_attempt_at = $nextAttemptAt;
                $run->save();
            } elseif ($this->runStateMachine->canTransition($run->run_state, RunState::Dead)) {
                $run->run_state = RunState::Dead;
                $run->finished_at = $now;
                $run->final_error_code = 'interrupted';
                $run->save();
            }

            $count++;
        }

        return $count;
    }
}
