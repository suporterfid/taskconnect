<?php

namespace App\Domain\Execution;

use App\Domain\Execution\Enums\AttemptState;

final class AttemptStateMachine
{
    /** @var array<string, list<AttemptState>> */
    private const TRANSITIONS = [
        'pending' => [AttemptState::Running, AttemptState::Interrupted, AttemptState::Blocked],
        'running' => [
            AttemptState::Succeeded,
            AttemptState::FailedRetryable,
            AttemptState::FailedTerminal,
            AttemptState::TimedOut,
            AttemptState::Interrupted,
            AttemptState::Blocked,
        ],
        'succeeded' => [],
        'failed_retryable' => [],
        'failed_terminal' => [],
        'timed_out' => [],
        'interrupted' => [],
        'blocked' => [],
    ];

    public function assertCanTransition(AttemptState $from, AttemptState $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new InvalidStateTransitionException('attempt', $from->value, $to->value);
        }
    }

    public function canTransition(AttemptState $from, AttemptState $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function transition(AttemptState $from, AttemptState $to): AttemptState
    {
        $this->assertCanTransition($from, $to);

        return $to;
    }
}
