<?php

namespace Tests\Feature\Phase1;

use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Enums\TriggerType;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class DlqApiTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_list_and_replay_dead_runs(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $task = Task::factory()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'task_type' => 'document.convert',
            'created_by' => $admin->id,
        ]);

        $dead = TaskRun::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'task_id' => $task->id,
            'trigger_type' => TriggerType::Manual,
            'occurrence_key' => 'occ-dead-1',
            'idempotency_key' => 'idem-dead-1',
            'run_state' => RunState::Dead,
            'attempt_count' => 3,
            'finished_at' => now(),
            'final_error_code' => '500',
        ]);

        $list = $this->actingAs($admin)->getJson(
            $this->environmentRoute($tenant, $environment, '/dlq'),
        );
        $list->assertOk()
            ->assertJsonPath('data.0.id', $dead->public_id)
            ->assertJsonPath('data.0.task_type', 'document.convert');

        $replay = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/dlq/'.$dead->public_id.'/replay'),
        );
        $replay->assertStatus(202)
            ->assertJsonPath('data.run_state', 'pending');
        $this->assertNotSame($dead->public_id, $replay->json('data.id'));
    }
}
