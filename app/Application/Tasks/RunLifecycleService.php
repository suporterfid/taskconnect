<?php

namespace App\Application\Tasks;

use App\Domain\Execution\AttemptStateMachine;
use App\Domain\Execution\Enums\AttemptState;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\InvalidStateTransitionException;
use App\Domain\Execution\RunStateMachine;
use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use Illuminate\Support\Facades\DB;

final class RunLifecycleService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly RunStateMachine $runStateMachine,
        private readonly AttemptStateMachine $attemptStateMachine,
    ) {
    }

    public function cancel(TaskRun $run): TaskRun
    {
        if ($run->run_state->isTerminal()) {
            throw new InvalidStateTransitionException('run', $run->run_state->value, RunState::Cancelled->value);
        }

        $this->runStateMachine->assertCanTransition($run->run_state, RunState::Cancelled);
        $run->run_state = RunState::Cancelled;
        $run->finished_at = $this->clock->nowUtc();
        $run->next_attempt_at = null;
        $run->save();

        return $run->fresh(['attempts']);
    }

    public function manualRetry(TaskRun $run): TaskRunAttempt
    {
        if (! in_array($run->run_state, [RunState::RetryWait, RunState::Dead], true)) {
            throw new InvalidStateTransitionException('run', $run->run_state->value, RunState::Pending->value);
        }

        return DB::transaction(function () use ($run): TaskRunAttempt {
            $nextAttemptNumber = $run->attempt_count + 1;

            $attempt = TaskRunAttempt::query()->create([
                'tenant_id' => $run->tenant_id,
                'environment_id' => $run->environment_id,
                'task_run_id' => $run->id,
                'attempt_number' => $nextAttemptNumber,
                'attempt_state' => AttemptState::Pending,
            ]);

            $run->attempt_count = $nextAttemptNumber;
            $run->run_state = RunState::Pending;
            $run->next_attempt_at = $this->clock->nowUtc();
            $run->finished_at = null;
            $run->save();

            return $attempt;
        });
    }
}
