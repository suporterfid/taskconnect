<?php

namespace App\Domain\Scheduling;

use App\Infrastructure\Persistence\Eloquent\Task;

/**
 * Workspace fairness for claim selection (R12 + R17).
 *
 * Modes:
 * - `rr`  — weighted round-robin; each pick costs 1 (R12)
 * - `wfq` — deficit round-robin; each pick costs max(1, task.weight) (R17)
 *
 * Optional claim-time priority preemption (R17): up to N high-priority items
 * are selected first (still fairness-interleaved among themselves). Does not
 * cancel in-flight HTTP deliveries.
 */
final class WorkspaceFairnessInterleaver
{
    /**
     * @param  list<Task>  $tasks
     * @return list<Task>
     */
    public function interleave(array $tasks, ?int $workspaceWeight = null): array
    {
        return $this->interleaveItems(
            $tasks,
            static fn (Task $task): int => (int) $task->environment_id,
            static fn (Task $task): int => max(1, (int) ($task->weight ?? 1)),
            static fn (Task $task): int => (int) ($task->priority ?? 0),
            $workspaceWeight,
        );
    }

    /**
     * @param  list<object{environment_id: int}>  $items
     * @return list<object>
     */
    public function interleaveByEnvironmentId(array $items, ?int $workspaceWeight = null): array
    {
        return $this->interleaveItems(
            $items,
            static fn (object $item): int => (int) $item->environment_id,
            static function (object $item): int {
                $task = $item->task ?? null;
                if ($task !== null) {
                    return max(1, (int) ($task->weight ?? 1));
                }

                return 1;
            },
            static function (object $item): int {
                $task = $item->task ?? null;
                if ($task !== null) {
                    return (int) ($task->priority ?? 0);
                }

                return 0;
            },
            $workspaceWeight,
        );
    }

    /**
     * @template T of object
     *
     * @param  list<T>  $items
     * @param  callable(T): int  $workspaceId
     * @param  callable(T): int  $cost
     * @param  callable(T): int  $priority
     * @return list<T>
     */
    private function interleaveItems(
        array $items,
        callable $workspaceId,
        callable $cost,
        callable $priority,
        ?int $workspaceWeight,
    ): array {
        if ($items === [] || count($items) === 1) {
            return $items;
        }

        $quantum = max(1, $workspaceWeight ?? (int) config('scheduler.fairness_workspace_weight', 1));
        $mode = strtolower((string) config('scheduler.fairness_mode', 'wfq'));
        if ($mode !== 'rr' && $mode !== 'wfq') {
            $mode = 'wfq';
        }

        $preemptMin = config('scheduler.priority_preemption_min');
        $preemptSlots = max(0, (int) config('scheduler.priority_preemption_slots', 1));

        if ($preemptMin !== null && $preemptMin !== '' && $preemptSlots > 0) {
            $threshold = (int) $preemptMin;
            $high = [];
            foreach ($items as $item) {
                if ($priority($item) >= $threshold) {
                    $high[] = $item;
                }
            }

            if ($high !== []) {
                $preempted = array_slice(
                    $this->deficitRoundRobin($high, $workspaceId, $cost, $quantum, $mode),
                    0,
                    $preemptSlots,
                );
                $remaining = [];
                foreach ($items as $item) {
                    if (! in_array($item, $preempted, true)) {
                        $remaining[] = $item;
                    }
                }

                return array_merge(
                    $preempted,
                    $this->deficitRoundRobin($remaining, $workspaceId, $cost, $quantum, $mode),
                );
            }
        }

        return $this->deficitRoundRobin($items, $workspaceId, $cost, $quantum, $mode);
    }

    /**
     * @template T of object
     *
     * @param  list<T>  $items
     * @param  callable(T): int  $workspaceId
     * @param  callable(T): int  $cost
     * @return list<T>
     */
    private function deficitRoundRobin(
        array $items,
        callable $workspaceId,
        callable $cost,
        int $quantum,
        string $mode,
    ): array {
        if ($items === [] || count($items) === 1) {
            return $items;
        }

        /** @var array<int, list<T>> $queues */
        $queues = [];
        /** @var list<int> $workspaceOrder */
        $workspaceOrder = [];
        /** @var array<int, int> $deficit */
        $deficit = [];

        foreach ($items as $item) {
            $id = $workspaceId($item);
            if (! array_key_exists($id, $queues)) {
                $queues[$id] = [];
                $workspaceOrder[] = $id;
                $deficit[$id] = 0;
            }
            $queues[$id][] = $item;
        }

        if (count($queues) <= 1) {
            return $items;
        }

        $result = [];
        while ($queues !== []) {
            $stillActive = [];
            foreach ($workspaceOrder as $id) {
                if (! isset($queues[$id])) {
                    continue;
                }

                $deficit[$id] = ($deficit[$id] ?? 0) + $quantum;

                while ($queues[$id] !== []) {
                    $head = $queues[$id][0];
                    $pickCost = $mode === 'rr' ? 1 : max(1, $cost($head));
                    if ($pickCost > $deficit[$id]) {
                        break;
                    }
                    $result[] = array_shift($queues[$id]);
                    $deficit[$id] -= $pickCost;
                }

                if ($queues[$id] === []) {
                    unset($queues[$id], $deficit[$id]);
                } else {
                    $stillActive[] = $id;
                }
            }
            $workspaceOrder = $stillActive;
        }

        return $result;
    }
}
