<?php

namespace Tests\Unit\Scheduling;

use App\Application\Scheduling\InFlightCapacityTracker;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Enums\TaskDefinitionStatus;
use App\Domain\Execution\Enums\TriggerType;
use App\Domain\Scheduling\TaskTypeCatalog;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesScheduledTasks;
use Tests\TestCase;

class InFlightCapacityTrackerTest extends TestCase
{
    use CreatesScheduledTasks;
    use RefreshDatabase;

    public function test_can_accept_respects_per_type_cap_by_weight(): void
    {
        config([
            'task_types.global_inflight_ceiling' => 10,
            'task_types.types.document.convert.concurrency_cap' => 2,
            'task_types.types.document.convert.weight' => 1,
        ]);

        $catalog = new TaskTypeCatalog;
        $tracker = new InFlightCapacityTracker($catalog);

        $task = Task::factory()->create([
            'task_type' => 'document.convert',
            'priority' => 5,
            'weight' => 1,
            'definition_status' => TaskDefinitionStatus::Active,
        ]);

        $this->assertTrue($tracker->canAccept($task));
        $tracker->reserve($task);
        $tracker->reserve($task);
        // Per-type cap is a concurrency count (2 jobs), not weight units.
        $this->assertFalse($tracker->canAccept($task));
    }

    public function test_heavy_weight_fits_when_type_cap_allows_one_job(): void
    {
        config([
            'task_types.global_inflight_ceiling' => 4,
            'task_types.types.site.crawl.concurrency_cap' => 1,
            'task_types.types.site.crawl.weight' => 2,
        ]);

        $catalog = new TaskTypeCatalog;
        $tracker = new InFlightCapacityTracker($catalog);
        $task = Task::factory()->create([
            'task_type' => 'site.crawl',
            'priority' => 4,
            'weight' => 2,
            'definition_status' => TaskDefinitionStatus::Active,
        ]);

        $this->assertTrue($tracker->canAccept($task));
        $tracker->reserve($task);
        $this->assertFalse($tracker->canAccept($task));
        $this->assertSame(2, $tracker->remainingGlobal());
    }

    public function test_refresh_from_database_counts_pending_and_running_runs(): void
    {
        config([
            'task_types.global_inflight_ceiling' => 4,
            'task_types.types.document.convert.concurrency_cap' => 2,
        ]);

        [$tenant, $environment, $user] = $this->createTenantContext();
        $task = Task::factory()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'created_by' => $user->id,
            'task_type' => 'document.convert',
            'priority' => 5,
            'weight' => 1,
            'definition_status' => TaskDefinitionStatus::Active,
        ]);

        TaskRun::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'task_id' => $task->id,
            'trigger_type' => TriggerType::Scheduled,
            'scheduled_for' => now()->utc(),
            'occurrence_key' => 'occ-1',
            'idempotency_key' => 'idem-1',
            'run_state' => RunState::Pending,
            'attempt_count' => 1,
        ]);

        TaskRun::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'task_id' => $task->id,
            'trigger_type' => TriggerType::Scheduled,
            'scheduled_for' => now()->utc()->addMinute(),
            'occurrence_key' => 'occ-2',
            'idempotency_key' => 'idem-2',
            'run_state' => RunState::Running,
            'attempt_count' => 1,
        ]);

        $tracker = new InFlightCapacityTracker(new TaskTypeCatalog);
        $tracker->refreshFromDatabase();

        // Two open runs of weight 1 each ⇒ convert cap 2 is full.
        $this->assertFalse($tracker->canAccept($task));
        $this->assertSame(2, 4 - $tracker->remainingGlobal());
    }
}
