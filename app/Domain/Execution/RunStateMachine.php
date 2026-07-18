<?php

namespace App\Domain\Execution;

use App\Domain\Execution\Enums\RunState;

final class RunStateMachine
{
    /** @var array<string, list<RunState>> */
    private const TRANSITIONS = [
        'pending' => [RunState::Running, RunState::Cancelled, RunState::Blocked],
        'running' => [RunState::Succeeded, RunState::RetryWait, RunState::Dead, RunState::Blocked, RunState::Cancelled],
        'retry_wait' => [RunState::Running, RunState::Cancelled, RunState::Dead],
        'succeeded' => [],
        'dead' => [],
        'cancelled' => [],
        'blocked' => [],
    ];

    public function assertCanTransition(RunState $from, RunState $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new InvalidStateTransitionException('run', $from->value, $to->value);
        }
    }

    public function canTransition(RunState $from, RunState $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function transition(RunState $from, RunState $to): RunState
    {
        $this->assertCanTransition($from, $to);

        return $to;
    }
}
