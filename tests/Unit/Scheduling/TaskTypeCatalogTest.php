<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\TaskTypeCatalog;
use Tests\TestCase;

class TaskTypeCatalogTest extends TestCase
{
    public function test_definition_resolves_named_type_defaults(): void
    {
        $catalog = new TaskTypeCatalog;
        $convert = $catalog->definition('document.convert');

        $this->assertSame(5, $convert['priority']);
        $this->assertSame(1, $convert['weight']);
        $this->assertSame(2, $convert['concurrency_cap']);
        $this->assertSame('internal', $convert['egress_profile']);
    }

    public function test_unknown_or_empty_type_falls_back_to_default(): void
    {
        $catalog = new TaskTypeCatalog;

        $this->assertSame($catalog->definition('default'), $catalog->definition(null));
        $this->assertSame($catalog->definition('default'), $catalog->definition('not.a.type'));
    }

    public function test_resolve_task_attributes_applies_overrides(): void
    {
        $catalog = new TaskTypeCatalog;
        $attrs = $catalog->resolveTaskAttributes('note.reminder', [
            'priority' => 9,
            'timeout_ms' => 5000,
        ]);

        $this->assertSame('note.reminder', $attrs['task_type']);
        $this->assertSame(9, $attrs['priority']);
        $this->assertSame(1, $attrs['weight']);
        $this->assertSame(5000, $attrs['timeout_ms']);
        $this->assertSame('internal', $attrs['egress_profile']);
    }

    public function test_global_ceiling_defaults_to_four(): void
    {
        $this->assertSame(4, (new TaskTypeCatalog)->globalCeiling());
    }
}
