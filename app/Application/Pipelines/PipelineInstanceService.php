<?php

namespace App\Application\Pipelines;

use App\Domain\Execution\Enums\AttemptState;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Enums\TaskDefinitionStatus;
use App\Domain\Execution\Enums\TriggerType;
use App\Domain\Execution\IdempotencyKeyGenerator;
use App\Domain\Execution\OccurrenceKeyGenerator;
use App\Domain\Execution\RetryPolicy;
use App\Domain\Pipelines\InvalidPipelineTemplateException;
use App\Domain\Pipelines\PipelineInstanceStatus;
use App\Domain\Pipelines\PipelineNodeStatus;
use App\Domain\Pipelines\PipelineTemplateCatalog;
use App\Domain\Scheduling\TaskTypeCatalog;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\PipelineInstance;
use App\Infrastructure\Persistence\Eloquent\PipelineInstanceNode;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Support\Facades\DB;

final class PipelineInstanceService
{
    public function __construct(
        private readonly PipelineTemplateCatalog $templates,
        private readonly TaskTypeCatalog $taskTypes,
        private readonly IdempotencyKeyGenerator $idempotencyKeyGenerator,
        private readonly OccurrenceKeyGenerator $occurrenceKeyGenerator,
    ) {
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodeConfigs  delivery config keyed by template node_key
     */
    public function create(
        Tenant $tenant,
        Environment $environment,
        string $templateName,
        array $nodeConfigs,
        ?int $userId = null,
    ): PipelineInstance {
        if (! $this->templates->isKnown($templateName)) {
            throw new InvalidPipelineTemplateException(sprintf('Unknown pipeline template "%s".', $templateName));
        }

        $template = $this->templates->get($templateName);
        $this->assertNodeConfigsCoverTemplate($template['nodes'], $nodeConfigs);

        return DB::transaction(function () use ($tenant, $environment, $templateName, $template, $nodeConfigs, $userId): PipelineInstance {
            /** @var PipelineInstance $instance */
            $instance = PipelineInstance::query()->create([
                'tenant_id' => $tenant->id,
                'environment_id' => $environment->id,
                'template_name' => $templateName,
                'status' => PipelineInstanceStatus::Pending,
                'input_json' => ['nodes' => $nodeConfigs],
                'created_by' => $userId,
            ]);

            $nodeModels = [];
            foreach ($template['nodes'] as $nodeKey => $definition) {
                $nodeModels[$nodeKey] = PipelineInstanceNode::query()->create([
                    'pipeline_instance_id' => $instance->id,
                    'node_key' => $nodeKey,
                    'task_type' => $definition['task_type'],
                    'status' => PipelineNodeStatus::Pending,
                    'depends_on_json' => $definition['depends_on'],
                    'on_success' => $definition['on_success'],
                    'on_failure' => $definition['on_failure'],
                ]);
            }

            $roots = $this->templates->rootNodeKeys($template['nodes']);
            foreach ($roots as $rootKey) {
                $this->materializeNode($instance, $nodeModels[$rootKey], $nodeConfigs[$rootKey]);
            }

            $instance->status = PipelineInstanceStatus::Running;
            $instance->save();

            return $instance->fresh(['nodes.task', 'nodes.taskRun', 'environment']);
        });
    }

    /**
     * @param  array<string, mixed>  $delivery
     */
    public function materializeNode(
        PipelineInstance $instance,
        PipelineInstanceNode $node,
        array $delivery,
    ): TaskRun {
        if ($node->task_run_id !== null) {
            return TaskRun::query()->findOrFail($node->task_run_id);
        }

        if (! in_array($node->status, [PipelineNodeStatus::Pending, PipelineNodeStatus::Ready], true)) {
            if ($node->task_run_id !== null) {
                return TaskRun::query()->findOrFail($node->task_run_id);
            }
        }

        $governance = $this->taskTypes->resolveTaskAttributes($node->task_type);
        $method = strtoupper((string) ($delivery['method'] ?? 'POST'));
        $url = (string) ($delivery['url_or_path'] ?? $delivery['url'] ?? $delivery['path'] ?? '');
        if ($url === '') {
            throw new InvalidPipelineTemplateException(sprintf(
                'Node "%s" requires url_or_path (or url/path) in the instance payload.',
                $node->node_key,
            ));
        }

        $body = $delivery['body'] ?? $delivery['body_template'] ?? null;
        $bodyTemplate = null;
        if ($body !== null) {
            $bodyTemplate = is_string($body) ? $body : json_encode($body);
        }

        $retryPolicy = RetryPolicy::default()->toArray();
        $retryPolicy['max_attempts'] = $governance['max_attempts'];

        $task = Task::query()->create([
            'tenant_id' => $instance->tenant_id,
            'environment_id' => $instance->environment_id,
            'name' => sprintf('pipeline:%s:%s', $instance->template_name, $node->node_key),
            'description' => sprintf('Materialized from pipeline instance %s node %s', $instance->public_id, $node->node_key),
            'definition_status' => TaskDefinitionStatus::Active,
            'task_type' => $governance['task_type'],
            'priority' => $governance['priority'],
            'weight' => $governance['weight'],
            'timeout_ms' => $governance['timeout_ms'],
            'egress_profile' => array_key_exists('egress_profile', $delivery)
                ? (string) $delivery['egress_profile']
                : $governance['egress_profile'],
            'method' => $method,
            'url_or_path' => $url,
            'headers_json' => is_array($delivery['headers'] ?? null) ? $delivery['headers'] : [],
            'query_json' => is_array($delivery['query'] ?? null) ? $delivery['query'] : [],
            'body_template' => $bodyTemplate,
            'content_type' => isset($delivery['content_type']) ? (string) $delivery['content_type'] : null,
            'timezone' => 'UTC',
            'retry_policy_json' => $retryPolicy,
            'next_run_at' => null,
            'created_by' => $instance->created_by,
            'updated_by' => $instance->created_by,
        ]);

        $idempotencyKey = $this->idempotencyKeyGenerator->forManualRun(
            sprintf('pipe:%s:%s', $instance->public_id, $node->node_key),
        );
        $occurrenceKey = $this->occurrenceKeyGenerator->forManual($idempotencyKey);

        $run = TaskRun::query()->create([
            'tenant_id' => $instance->tenant_id,
            'environment_id' => $instance->environment_id,
            'task_id' => $task->id,
            'pipeline_instance_id' => $instance->id,
            'pipeline_node_id' => $node->id,
            'trigger_type' => TriggerType::Manual,
            'scheduled_for' => null,
            'occurrence_key' => $occurrenceKey,
            'idempotency_key' => $idempotencyKey,
            'run_state' => RunState::Pending,
            'attempt_count' => 1,
        ]);

        TaskRunAttempt::query()->create([
            'tenant_id' => $instance->tenant_id,
            'environment_id' => $instance->environment_id,
            'task_run_id' => $run->id,
            'attempt_number' => 1,
            'attempt_state' => AttemptState::Pending,
        ]);

        $node->task_id = $task->id;
        $node->task_run_id = $run->id;
        $node->status = PipelineNodeStatus::Ready;
        $node->save();

        return $run->fresh(['attempts', 'task']);
    }

    /**
     * @param  array<string, array{task_type: string, depends_on: list<string>, on_success: string|null, on_failure: string|null}>  $templateNodes
     * @param  array<string, array<string, mixed>>  $nodeConfigs
     */
    private function assertNodeConfigsCoverTemplate(array $templateNodes, array $nodeConfigs): void
    {
        foreach (array_keys($templateNodes) as $nodeKey) {
            if (! array_key_exists($nodeKey, $nodeConfigs) || ! is_array($nodeConfigs[$nodeKey])) {
                throw new InvalidPipelineTemplateException(sprintf(
                    'Instance payload must include a nodes.%s delivery config.',
                    $nodeKey,
                ));
            }
        }
    }
}
