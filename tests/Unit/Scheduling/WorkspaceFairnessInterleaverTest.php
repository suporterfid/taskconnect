<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\WorkspaceFairnessInterleaver;
use App\Infrastructure\Persistence\Eloquent\Task;
use Tests\TestCase;

class WorkspaceFairnessInterleaverTest extends TestCase
{
    public function test_interleaves_workspaces_round_robin(): void
    {
        $tasks = [
            $this->fakeTask(1, 10),
            $this->fakeTask(1, 11),
            $this->fakeTask(1, 12),
            $this->fakeTask(2, 20),
            $this->fakeTask(2, 21),
            $this->fakeTask(3, 30),
        ];

        $out = (new WorkspaceFairnessInterleaver)->interleave($tasks, 1);
        $workspaceSequence = array_map(static fn (Task $t) => $t->environment_id, $out);

        $this->assertSame([1, 2, 3, 1, 2, 1], $workspaceSequence);
        $this->assertSame([10, 20, 30, 11, 21, 12], array_map(static fn (Task $t) => $t->id, $out));
    }

    public function test_weight_two_gives_two_picks_per_round(): void
    {
        $tasks = [
            $this->fakeTask(1, 10),
            $this->fakeTask(1, 11),
            $this->fakeTask(1, 12),
            $this->fakeTask(2, 20),
            $this->fakeTask(2, 21),
        ];

        $out = (new WorkspaceFairnessInterleaver)->interleave($tasks, 2);
        $workspaceSequence = array_map(static fn (Task $t) => $t->environment_id, $out);

        $this->assertSame([1, 1, 2, 2, 1], $workspaceSequence);
    }

    private function fakeTask(int $environmentId, int $id): Task
    {
        $task = new Task;
        $task->id = $id;
        $task->environment_id = $environmentId;

        return $task;
    }
}
