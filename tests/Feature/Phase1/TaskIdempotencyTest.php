<?php

namespace Tests\Feature\Phase1;

use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class TaskIdempotencyTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_create_task_twice_with_same_idempotency_key_returns_same_task(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $payload = $this->draftTaskPayload('Idempotent Hook');
        $headers = ['Idempotency-Key' => 'create-task-key-1'];

        $first = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks'),
            $payload,
            $headers,
        );

        $first->assertCreated();
        $taskId = $first->json('data.id');

        $second = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks'),
            $payload,
            $headers,
        );

        $second->assertCreated()
            ->assertJsonPath('data.id', $taskId);

        $this->assertSame(1, Task::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_same_idempotency_key_with_different_payload_returns_conflict(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $headers = ['Idempotency-Key' => 'create-task-key-conflict'];

        $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks'),
            $this->draftTaskPayload('First Name'),
            $headers,
        )->assertCreated();

        $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks'),
            $this->draftTaskPayload('Different Name'),
            $headers,
        )->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');
    }

    public function test_run_now_twice_with_same_idempotency_key_returns_same_run(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $taskId = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks'),
            $this->draftTaskPayload('Run Now Hook'),
        )->json('data.id');

        $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks/'.$taskId.'/activate'),
        )->assertOk();

        $headers = ['Idempotency-Key' => 'run-now-key-1'];
        $base = $this->environmentRoute($tenant, $environment, '/tasks/'.$taskId.'/run-now');

        $first = $this->actingAs($admin)->postJson($base, [], $headers);
        $first->assertStatus(202);
        $runId = $first->json('data.id');

        $second = $this->actingAs($admin)->postJson($base, [], $headers);
        $second->assertStatus(202)
            ->assertJsonPath('data.id', $runId);

        $this->assertSame(1, TaskRun::query()->where('task_id', Task::query()->where('public_id', $taskId)->value('id'))->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function draftTaskPayload(string $name): array
    {
        return [
            'name' => $name,
            'method' => 'POST',
            'url' => 'https://example.com/hook',
            'schedule' => [
                'kind' => 'daily_at',
                'timezone' => 'UTC',
                'time' => '09:30',
            ],
        ];
    }
}
