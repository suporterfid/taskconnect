<?php

namespace Tests\Unit\Pipelines;

use App\Domain\Pipelines\InvalidPipelineTemplateException;
use App\Domain\Pipelines\PipelineDagValidator;
use App\Domain\Pipelines\PipelineTemplateCatalog;
use Tests\TestCase;

class PipelineDagValidatorTest extends TestCase
{
    public function test_linear_dag_is_valid(): void
    {
        $validator = new PipelineDagValidator;
        $validator->assertValid([
            'a' => ['task_type' => 'document.convert', 'depends_on' => [], 'on_success' => 'b', 'on_failure' => null],
            'b' => ['task_type' => 'kb.index', 'depends_on' => ['a'], 'on_success' => 'c', 'on_failure' => null],
            'c' => ['task_type' => 'publish.build', 'depends_on' => ['b'], 'on_success' => null, 'on_failure' => null],
        ]);

        $this->assertTrue(true);
    }

    public function test_cycle_via_on_success_is_rejected(): void
    {
        $this->expectException(InvalidPipelineTemplateException::class);
        $this->expectExceptionMessage('cycle');

        (new PipelineDagValidator)->assertValid([
            'a' => ['task_type' => 'document.convert', 'depends_on' => [], 'on_success' => 'b', 'on_failure' => null],
            'b' => ['task_type' => 'kb.index', 'depends_on' => [], 'on_success' => 'a', 'on_failure' => null],
        ]);
    }

    public function test_cycle_via_depends_on_is_rejected(): void
    {
        $this->expectException(InvalidPipelineTemplateException::class);

        (new PipelineDagValidator)->assertValid([
            'a' => ['task_type' => 'document.convert', 'depends_on' => ['b'], 'on_success' => null, 'on_failure' => null],
            'b' => ['task_type' => 'kb.index', 'depends_on' => ['a'], 'on_success' => null, 'on_failure' => null],
        ]);
    }

    public function test_unknown_dependency_is_rejected(): void
    {
        $this->expectException(InvalidPipelineTemplateException::class);
        $this->expectExceptionMessage('unknown node');

        (new PipelineDagValidator)->assertValid([
            'a' => ['task_type' => 'document.convert', 'depends_on' => ['missing'], 'on_success' => null, 'on_failure' => null],
        ]);
    }

    public function test_shipped_convert_index_publish_template_is_valid(): void
    {
        $catalog = new PipelineTemplateCatalog;
        $template = $catalog->get('convert-index-publish');

        $this->assertSame(['convert'], $catalog->rootNodeKeys($template['nodes']));
        $this->assertSame('kb.index', $template['nodes']['index']['task_type']);
        $this->assertSame('publish', $template['nodes']['index']['on_success']);
    }
}
