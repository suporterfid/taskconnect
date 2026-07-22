<?php

namespace App\Domain\Scheduling;

use App\Infrastructure\Persistence\Eloquent\Task;

/**
 * Weighted round-robin interleave of due tasks across workspaces (R12).
 *
 * Within each workspace, input order (priority / next_run_at) is preserved.
 * Across workspaces, each gets `weight` picks per round so one workspace's
 * backlog cannot monopolize a claim batch.
 */
final class WorkspaceFairnessInterleaver
{
    /**
     * @param  list<Task>  $tasks
     * @return list<Task>
     */
    public function interleave(array $tasks, ?int $workspaceWeight = null): array
    {
        if ($tasks === [] || count($tasks) === 1) {
            return $tasks;
        }

        $weight = max(1, $workspaceWeight ?? (int) config('scheduler.fairness_workspace_weight', 1));

        /** @var array<int, list<Task>> $queues */
        $queues = [];
        /** @var list<int> $workspaceOrder */
        $workspaceOrder = [];

        foreach ($tasks as $task) {
            $workspaceId = (int) $task->environment_id;
            if (! array_key_exists($workspaceId, $queues)) {
                $queues[$workspaceId] = [];
                $workspaceOrder[] = $workspaceId;
            }
            $queues[$workspaceId][] = $task;
        }

        if (count($queues) <= 1) {
            return $tasks;
        }

        $result = [];
        while ($queues !== []) {
            $stillActive = [];
            foreach ($workspaceOrder as $workspaceId) {
                if (! isset($queues[$workspaceId])) {
                    continue;
                }

                for ($i = 0; $i < $weight; $i++) {
                    if ($queues[$workspaceId] === []) {
                        break;
                    }
                    $result[] = array_shift($queues[$workspaceId]);
                }

                if ($queues[$workspaceId] === []) {
                    unset($queues[$workspaceId]);
                } else {
                    $stillActive[] = $workspaceId;
                }
            }
            $workspaceOrder = $stillActive;
        }

        return $result;
    }

    /**
     * @param  list<object{environment_id: int}>  $items
     * @return list<object>
     */
    public function interleaveByEnvironmentId(array $items, ?int $workspaceWeight = null): array
    {
        if ($items === [] || count($items) === 1) {
            return $items;
        }

        $weight = max(1, $workspaceWeight ?? (int) config('scheduler.fairness_workspace_weight', 1));

        /** @var array<int, list<object>> $queues */
        $queues = [];
        /** @var list<int> $workspaceOrder */
        $workspaceOrder = [];

        foreach ($items as $item) {
            $workspaceId = (int) $item->environment_id;
            if (! array_key_exists($workspaceId, $queues)) {
                $queues[$workspaceId] = [];
                $workspaceOrder[] = $workspaceId;
            }
            $queues[$workspaceId][] = $item;
        }

        if (count($queues) <= 1) {
            return $items;
        }

        $result = [];
        while ($queues !== []) {
            $stillActive = [];
            foreach ($workspaceOrder as $workspaceId) {
                if (! isset($queues[$workspaceId])) {
                    continue;
                }

                for ($i = 0; $i < $weight; $i++) {
                    if ($queues[$workspaceId] === []) {
                        break;
                    }
                    $result[] = array_shift($queues[$workspaceId]);
                }

                if ($queues[$workspaceId] === []) {
                    unset($queues[$workspaceId]);
                } else {
                    $stillActive[] = $workspaceId;
                }
            }
            $workspaceOrder = $stillActive;
        }

        return $result;
    }
}
