<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\WorkspaceFairnessInterleaver;
use App\Infrastructure\Persistence\Eloquent\Task;
use Tests\TestCase;

class WorkspaceFairnessInterleaverTest extends TestCase
{
    public function test_interleaves_workspaces_round_robin(): void
    {
        config(['scheduler.fairness_mode' => 'rr']);

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
        config(['scheduler.fairness_mode' => 'rr']);

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

    public function test_wfq_charges_task_weight_against_quantum(): void
    {
        config(['scheduler.fairness_mode' => 'wfq']);

        $tasks = [
            $this->fakeTask(1, 10, weight: 3),
            $this->fakeTask(1, 11, weight: 3),
            $this->fakeTask(2, 20, weight: 1),
            $this->fakeTask(2, 21, weight: 1),
        ];

        $out = (new WorkspaceFairnessInterleaver)->interleave($tasks, 1);
        $ids = array_map(static fn (Task $t) => $t->id, $out);

        // Quantum 1: workspace 1 must accumulate before a weight-3 pick; workspace 2 goes sooner.
        $this->assertSame(20, $ids[0]);
        $this->assertContains(10, array_slice($ids, 0, 3));
    }

    public function test_priority_preemption_places_high_priority_first(): void
    {
        config([
            'scheduler.fairness_mode' => 'rr',
            'scheduler.priority_preemption_min' => 8,
            'scheduler.priority_preemption_slots' => 1,
        ]);

        $tasks = [
            $this->fakeTask(1, 10, priority: 5),
            $this->fakeTask(1, 11, priority: 5),
            $this->fakeTask(2, 20, priority: 9),
        ];

        $out = (new WorkspaceFairnessInterleaver)->interleave($tasks, 1);

        $this->assertSame(20, $out[0]->id);
        $this->assertSame([20, 10, 11], array_map(static fn (Task $t) => $t->id, $out));
    }

    private function fakeTask(int $environmentId, int $id, int $weight = 1, int $priority = 0): Task
    {
        $task = new Task;
        $task->id = $id;
        $task->environment_id = $environmentId;
        $task->weight = $weight;
        $task->priority = $priority;

        return $task;
    }
}
