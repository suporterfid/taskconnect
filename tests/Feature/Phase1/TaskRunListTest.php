<?php

namespace Tests\Feature\Phase1;

use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Enums\TaskDefinitionStatus;
use App\Domain\Execution\Enums\TriggerType;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class TaskRunListTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_lists_runs_with_default_meta(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $task = $this->createTask($tenant->id, $environment->id, $admin->id);
        $this->createRun($task, 'occ-1', '2026-07-18 12:00:00');

        $response = $this->actingAs($admin)->getJson(
            $this->environmentRoute($tenant, $environment, '/task-runs')
        );

        $response->assertOk()
            ->assertJsonPath('meta.limit', 50)
            ->assertJsonPath('meta.next_before', null)
            ->assertJsonCount(1, 'data');
    }

    public function test_paginates_with_limit_and_before_cursor(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $task = $this->createTask($tenant->id, $environment->id, $admin->id);

        $this->createRun($task, 'occ-older', '2026-07-18 10:00:00');
        $this->createRun($task, 'occ-mid', '2026-07-18 11:00:00');
        $this->createRun($task, 'occ-newer', '2026-07-18 12:00:00');

        $first = $this->actingAs($admin)->getJson(
            $this->environmentRoute($tenant, $environment, '/task-runs?limit=2')
        );

        $first->assertOk()
            ->assertJsonPath('meta.limit', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.idempotency_key', 'idem-occ-newer')
            ->assertJsonPath('data.1.idempotency_key', 'idem-occ-mid');

        $nextBefore = $first->json('meta.next_before');
        $this->assertSame('2026-07-18T11:00:00Z', $nextBefore);

        $second = $this->actingAs($admin)->getJson(
            $this->environmentRoute($tenant, $environment, '/task-runs?limit=2&before='.urlencode($nextBefore))
        );

        $second->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.idempotency_key', 'idem-occ-older')
            ->assertJsonPath('meta.next_before', null);
    }

    public function test_filters_by_task_public_id(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $taskA = $this->createTask($tenant->id, $environment->id, $admin->id, 'Task A');
        $taskB = $this->createTask($tenant->id, $environment->id, $admin->id, 'Task B');

        $this->createRun($taskA, 'occ-a', '2026-07-18 12:00:00');
        $this->createRun($taskB, 'occ-b', '2026-07-18 12:01:00');

        $response = $this->actingAs($admin)->getJson(
            $this->environmentRoute($tenant, $environment, '/task-runs?task_id='.$taskA->public_id)
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.task_id', $taskA->public_id);
    }

    private function createTask(int $tenantId, int $environmentId, int $userId, string $name = 'List Task'): Task
    {
        return Task::factory()->create([
            'tenant_id' => $tenantId,
            'environment_id' => $environmentId,
            'name' => $name,
            'definition_status' => TaskDefinitionStatus::Active,
            'url_or_path' => 'http://receiver:8080/hook',
            'created_by' => $userId,
        ]);
    }

    private function createRun(Task $task, string $occurrenceKey, string $createdAt): TaskRun
    {
        $run = TaskRun::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_id' => $task->id,
            'trigger_type' => TriggerType::Manual,
            'occurrence_key' => $occurrenceKey,
            'idempotency_key' => 'idem-'.$occurrenceKey,
            'run_state' => RunState::Succeeded,
            'attempt_count' => 1,
        ]);

        TaskRun::query()->where('id', $run->id)->update(['created_at' => $createdAt]);

        return $run->fresh();
    }
}
