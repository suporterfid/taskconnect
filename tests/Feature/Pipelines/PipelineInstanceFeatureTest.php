<?php

namespace Tests\Feature\Pipelines;

use App\Application\Pipelines\PipelineSettlementService;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Pipelines\PipelineInstanceStatus;
use App\Domain\Pipelines\PipelineNodeStatus;
use App\Infrastructure\Persistence\Eloquent\PipelineInstanceNode;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class PipelineInstanceFeatureTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_create_instance_materializes_root_only(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $response = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/pipelines/convert-index-publish/instances'),
            $this->pipelinePayload(),
        );

        $response->assertCreated()
            ->assertJsonPath('data.template_name', 'convert-index-publish')
            ->assertJsonPath('data.status', PipelineInstanceStatus::Running->value);

        $nodes = collect($response->json('data.nodes'))->keyBy('node_key');
        $this->assertSame(PipelineNodeStatus::Ready->value, $nodes['convert']['status']);
        $this->assertNotNull($nodes['convert']['task_run_id']);
        $this->assertSame(PipelineNodeStatus::Pending->value, $nodes['index']['status']);
        $this->assertNull($nodes['index']['task_run_id']);
        $this->assertSame(PipelineNodeStatus::Pending->value, $nodes['publish']['status']);
        $this->assertNull($nodes['publish']['task_run_id']);
    }

    public function test_success_enqueues_on_success_successor(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $created = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/pipelines/convert-index-publish/instances'),
            $this->pipelinePayload(),
        )->assertCreated();

        $convertRunId = collect($created->json('data.nodes'))->firstWhere('node_key', 'convert')['task_run_id'];
        $run = TaskRun::query()->where('public_id', $convertRunId)->firstOrFail();
        $run->run_state = RunState::Succeeded;
        $run->save();

        app(PipelineSettlementService::class)->handleSettledRun($run->fresh());

        $index = PipelineInstanceNode::query()
            ->where('pipeline_instance_id', $run->pipeline_instance_id)
            ->where('node_key', 'index')
            ->firstOrFail();

        $this->assertSame(PipelineNodeStatus::Ready, $index->status);
        $this->assertNotNull($index->task_run_id);

        $publish = PipelineInstanceNode::query()
            ->where('pipeline_instance_id', $run->pipeline_instance_id)
            ->where('node_key', 'publish')
            ->firstOrFail();
        $this->assertSame(PipelineNodeStatus::Pending, $publish->status);
        $this->assertNull($publish->task_run_id);
    }

    public function test_failure_without_on_failure_halts_dependents_and_leaves_run_dead(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $created = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/pipelines/convert-index-publish/instances'),
            $this->pipelinePayload(),
        )->assertCreated();

        $convertRunId = collect($created->json('data.nodes'))->firstWhere('node_key', 'convert')['task_run_id'];
        $run = TaskRun::query()->where('public_id', $convertRunId)->firstOrFail();
        $run->run_state = RunState::Dead;
        $run->final_error_code = '500';
        $run->save();

        app(PipelineSettlementService::class)->handleSettledRun($run->fresh());

        $nodes = PipelineInstanceNode::query()
            ->where('pipeline_instance_id', $run->pipeline_instance_id)
            ->get()
            ->keyBy('node_key');

        $this->assertSame(PipelineNodeStatus::Failed, $nodes['convert']->status);
        $this->assertSame(PipelineNodeStatus::Halted, $nodes['index']->status);
        $this->assertSame(PipelineNodeStatus::Halted, $nodes['publish']->status);
        $this->assertNull($nodes['index']->task_run_id);

        $run->refresh();
        $this->assertSame(RunState::Dead, $run->run_state);

        $show = $this->actingAs($admin)->getJson(
            $this->environmentRoute(
                $tenant,
                $environment,
                '/pipelines/convert-index-publish/instances/'.$created->json('data.id'),
            ),
        );
        $show->assertOk()->assertJsonPath('data.status', PipelineInstanceStatus::Failed->value);
    }

    public function test_unknown_template_returns_422(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/pipelines/does-not-exist/instances'),
            ['nodes' => ['a' => ['url_or_path' => 'http://receiver:8080/x']]],
        )->assertStatus(422)
            ->assertJsonPath('error.code', 'pipeline_template_invalid');
    }

    public function test_cyclic_template_rejected_at_create(): void
    {
        config()->set('pipeline_templates.templates.cyclic', [
            'description' => 'bad',
            'nodes' => [
                'a' => ['task_type' => 'document.convert', 'depends_on' => [], 'on_success' => 'b', 'on_failure' => null],
                'b' => ['task_type' => 'kb.index', 'depends_on' => [], 'on_success' => 'a', 'on_failure' => null],
            ],
        ]);

        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/pipelines/cyclic/instances'),
            [
                'nodes' => [
                    'a' => ['url_or_path' => 'http://receiver:8080/a'],
                    'b' => ['url_or_path' => 'http://receiver:8080/b'],
                ],
            ],
        )->assertStatus(422)
            ->assertJsonPath('error.code', 'pipeline_template_invalid');
    }

    /**
     * @return array{nodes: array<string, array{url_or_path: string, method: string}>}
     */
    private function pipelinePayload(): array
    {
        return [
            'nodes' => [
                'convert' => [
                    'method' => 'POST',
                    'url_or_path' => 'http://receiver:8080/convert',
                    'body' => ['file_id' => 'f1'],
                ],
                'index' => [
                    'method' => 'POST',
                    'url_or_path' => 'http://receiver:8080/index',
                    'body' => ['doc_id' => 'd1'],
                ],
                'publish' => [
                    'method' => 'POST',
                    'url_or_path' => 'http://receiver:8080/publish',
                ],
            ],
        ];
    }
}
