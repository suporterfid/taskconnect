<?php

namespace App\Application\Pipelines;

use App\Domain\Execution\Enums\RunState;
use App\Domain\Pipelines\PipelineInstanceStatus;
use App\Domain\Pipelines\PipelineNodeStatus;
use App\Infrastructure\Persistence\Eloquent\PipelineInstance;
use App\Infrastructure\Persistence\Eloquent\PipelineInstanceNode;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use Illuminate\Support\Facades\DB;

/**
 * Advances pipeline DAGs when a materialized node run settles (R10).
 */
final class PipelineSettlementService
{
    public function __construct(
        private readonly PipelineInstanceService $instances,
    ) {
    }

    public function handleSettledRun(TaskRun $run): void
    {
        if ($run->pipeline_node_id === null || $run->pipeline_instance_id === null) {
            return;
        }

        if (! in_array($run->run_state, [RunState::Succeeded, RunState::Dead, RunState::Blocked, RunState::Cancelled], true)) {
            return;
        }

        DB::transaction(function () use ($run): void {
            /** @var PipelineInstanceNode|null $node */
            $node = PipelineInstanceNode::query()
                ->where('id', $run->pipeline_node_id)
                ->lockForUpdate()
                ->first();

            if ($node === null) {
                return;
            }

            // Idempotent: already settled this node.
            if (in_array($node->status, [
                PipelineNodeStatus::Succeeded,
                PipelineNodeStatus::Failed,
                PipelineNodeStatus::Halted,
                PipelineNodeStatus::Skipped,
            ], true)) {
                $this->refreshInstanceStatus($node->pipeline_instance_id);

                return;
            }

            /** @var PipelineInstance $instance */
            $instance = PipelineInstance::query()
                ->where('id', $node->pipeline_instance_id)
                ->lockForUpdate()
                ->firstOrFail();

            $inputNodes = is_array($instance->input_json['nodes'] ?? null)
                ? $instance->input_json['nodes']
                : [];

            if ($run->run_state === RunState::Succeeded) {
                $node->status = PipelineNodeStatus::Succeeded;
                $node->save();
                $this->enqueueSuccessSuccessors($instance, $node, $inputNodes);
            } else {
                $node->status = PipelineNodeStatus::Failed;
                $node->save();
                $this->handleFailure($instance, $node, $inputNodes);
            }

            $this->refreshInstanceStatus($instance->id);
        });
    }

    /**
     * @param  array<string, array<string, mixed>>  $inputNodes
     */
    private function enqueueSuccessSuccessors(
        PipelineInstance $instance,
        PipelineInstanceNode $node,
        array $inputNodes,
    ): void {
        $targets = [];
        if (is_string($node->on_success) && $node->on_success !== '') {
            $targets[] = $node->on_success;
        }

        // Also unlock any fan-in dependents that list this node in depends_on.
        $dependents = PipelineInstanceNode::query()
            ->where('pipeline_instance_id', $instance->id)
            ->where('status', PipelineNodeStatus::Pending)
            ->get();

        foreach ($dependents as $dependent) {
            if (in_array($node->node_key, $dependent->dependsOn(), true)) {
                $targets[] = $dependent->node_key;
            }
        }

        foreach (array_unique($targets) as $targetKey) {
            $this->tryMaterializeIfReady($instance, $targetKey, $inputNodes);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $inputNodes
     */
    private function handleFailure(
        PipelineInstance $instance,
        PipelineInstanceNode $node,
        array $inputNodes,
    ): void {
        if (is_string($node->on_failure) && $node->on_failure !== '') {
            $this->tryMaterializeIfReady($instance, $node->on_failure, $inputNodes, ignoreDependsOn: true);
        }

        // Halt pending dependents that are not the explicit on_failure target.
        $haltSkip = $node->on_failure;
        $pending = PipelineInstanceNode::query()
            ->where('pipeline_instance_id', $instance->id)
            ->where('status', PipelineNodeStatus::Pending)
            ->get();

        foreach ($pending as $dependent) {
            if ($haltSkip !== null && $dependent->node_key === $haltSkip) {
                continue;
            }
            if ($this->isDownstreamOf($instance, $node->node_key, $dependent->node_key)) {
                $dependent->status = PipelineNodeStatus::Halted;
                $dependent->save();
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $inputNodes
     */
    private function tryMaterializeIfReady(
        PipelineInstance $instance,
        string $nodeKey,
        array $inputNodes,
        bool $ignoreDependsOn = false,
    ): void {
        /** @var PipelineInstanceNode|null $target */
        $target = PipelineInstanceNode::query()
            ->where('pipeline_instance_id', $instance->id)
            ->where('node_key', $nodeKey)
            ->lockForUpdate()
            ->first();

        if ($target === null) {
            return;
        }

        if ($target->task_run_id !== null
            || in_array($target->status, [
                PipelineNodeStatus::Ready,
                PipelineNodeStatus::Running,
                PipelineNodeStatus::Succeeded,
                PipelineNodeStatus::Failed,
                PipelineNodeStatus::Halted,
                PipelineNodeStatus::Skipped,
            ], true)) {
            return;
        }

        if (! $ignoreDependsOn && ! $this->dependenciesSatisfied($instance, $target)) {
            return;
        }

        $delivery = is_array($inputNodes[$nodeKey] ?? null) ? $inputNodes[$nodeKey] : [];
        $this->instances->materializeNode($instance, $target, $delivery);
    }

    private function dependenciesSatisfied(PipelineInstance $instance, PipelineInstanceNode $target): bool
    {
        foreach ($target->dependsOn() as $depKey) {
            $dep = PipelineInstanceNode::query()
                ->where('pipeline_instance_id', $instance->id)
                ->where('node_key', $depKey)
                ->first();

            if ($dep === null || $dep->status !== PipelineNodeStatus::Succeeded) {
                return false;
            }
        }

        return true;
    }

    private function isDownstreamOf(PipelineInstance $instance, string $fromKey, string $candidateKey): bool
    {
        if ($fromKey === $candidateKey) {
            return false;
        }

        $nodes = PipelineInstanceNode::query()
            ->where('pipeline_instance_id', $instance->id)
            ->get()
            ->keyBy('node_key');

        $visited = [];
        $stack = [$fromKey];

        while ($stack !== []) {
            $current = array_pop($stack);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            /** @var PipelineInstanceNode|null $currentNode */
            $currentNode = $nodes->get($current);
            if ($currentNode === null) {
                continue;
            }

            $nextKeys = [];
            if (is_string($currentNode->on_success) && $currentNode->on_success !== '') {
                $nextKeys[] = $currentNode->on_success;
            }
            if (is_string($currentNode->on_failure) && $currentNode->on_failure !== '') {
                $nextKeys[] = $currentNode->on_failure;
            }

            foreach ($nodes as $other) {
                if (in_array($current, $other->dependsOn(), true)) {
                    $nextKeys[] = $other->node_key;
                }
            }

            foreach (array_unique($nextKeys) as $next) {
                if ($next === $candidateKey) {
                    return true;
                }
                $stack[] = $next;
            }
        }

        return false;
    }

    private function refreshInstanceStatus(int $instanceId): void
    {
        $instance = PipelineInstance::query()->where('id', $instanceId)->lockForUpdate()->first();
        if ($instance === null) {
            return;
        }

        $nodes = PipelineInstanceNode::query()->where('pipeline_instance_id', $instanceId)->get();
        if ($nodes->isEmpty()) {
            return;
        }

        $statuses = $nodes->map(fn (PipelineInstanceNode $n) => $n->status)->all();

        $allTerminal = collect($statuses)->every(fn (PipelineNodeStatus $s) => in_array($s, [
            PipelineNodeStatus::Succeeded,
            PipelineNodeStatus::Failed,
            PipelineNodeStatus::Halted,
            PipelineNodeStatus::Skipped,
        ], true));

        if (! $allTerminal) {
            if ($instance->status !== PipelineInstanceStatus::Running) {
                $instance->status = PipelineInstanceStatus::Running;
                $instance->save();
            }

            return;
        }

        $anyFailed = collect($statuses)->contains(fn (PipelineNodeStatus $s) => in_array($s, [
            PipelineNodeStatus::Failed,
            PipelineNodeStatus::Halted,
        ], true));

        $instance->status = $anyFailed ? PipelineInstanceStatus::Failed : PipelineInstanceStatus::Succeeded;
        $instance->save();
    }
}
