<?php

namespace Tests\Unit\Execution;

use App\Domain\Execution\AttemptStateMachine;
use App\Domain\Execution\Enums\AttemptState;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\InvalidStateTransitionException;
use App\Domain\Execution\RunStateMachine;
use PHPUnit\Framework\TestCase;

class StateMachineTest extends TestCase
{
    public function test_run_state_machine_allows_valid_transitions(): void
    {
        $machine = new RunStateMachine;

        $this->assertSame(RunState::Running, $machine->transition(RunState::Pending, RunState::Running));
        $this->assertSame(RunState::Succeeded, $machine->transition(RunState::Running, RunState::Succeeded));
        $this->assertSame(RunState::RetryWait, $machine->transition(RunState::Running, RunState::RetryWait));
    }

    public function test_run_state_machine_rejects_illegal_transition(): void
    {
        $machine = new RunStateMachine;

        $this->expectException(InvalidStateTransitionException::class);
        $machine->assertCanTransition(RunState::Succeeded, RunState::Running);
    }

    public function test_attempt_state_machine_rejects_illegal_transition(): void
    {
        $machine = new AttemptStateMachine;

        $this->expectException(InvalidStateTransitionException::class);
        $machine->assertCanTransition(AttemptState::Succeeded, AttemptState::Running);
    }

    public function test_attempt_state_machine_allows_interrupted_from_running(): void
    {
        $machine = new AttemptStateMachine;

        $this->assertSame(
            AttemptState::Interrupted,
            $machine->transition(AttemptState::Running, AttemptState::Interrupted),
        );
    }
}
