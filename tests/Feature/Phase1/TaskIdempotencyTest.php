<?php

namespace Tests\Feature\Phase1;

use App\Application\Retention\RetentionCleaner;
use App\Infrastructure\Persistence\Eloquent\IdempotencyKey;
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

        $second->assertOk()
            ->assertJsonPath('data.id', $taskId);

        $this->assertSame(1, Task::query()->where('tenant_id', $tenant->id)->count());

        $record = IdempotencyKey::query()->where('key', 'create-task-key-1')->first();
        $this->assertNotNull($record);
        $this->assertSame($environment->id, $record->environment_id);
    }

    public function test_create_task_without_idempotency_key_is_rejected(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        // Use json() (not postJson) so TestCase does not auto-inject a key.
        $this->actingAs($admin)->json(
            'POST',
            $this->environmentRoute($tenant, $environment, '/tasks'),
            $this->draftTaskPayload('Missing Key'),
        )->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
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

    public function test_idempotency_key_is_scoped_per_workspace(): void
    {
        [$admin, $tenant, $development] = $this->createTenantAdmin();
        $staging = $tenant->environments()->where('slug', 'staging')->firstOrFail();
        $headers = ['Idempotency-Key' => 'shared-across-workspaces'];
        $payload = $this->draftTaskPayload('Scoped Hook');

        $dev = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $development, '/tasks'),
            $payload,
            $headers,
        )->assertCreated();

        $stg = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $staging, '/tasks'),
            $payload,
            $headers,
        )->assertCreated();

        $this->assertNotSame($dev->json('data.id'), $stg->json('data.id'));
        $this->assertSame(2, Task::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_expired_idempotency_key_can_be_reused(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $headers = ['Idempotency-Key' => 'expired-key-reuse'];
        $payload = $this->draftTaskPayload('Expired Hook');

        $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks'),
            $payload,
            $headers,
        )->assertCreated();

        IdempotencyKey::query()
            ->where('key', 'expired-key-reuse')
            ->update(['expires_at' => now()->subHour()]);

        $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks'),
            $this->draftTaskPayload('Expired Hook Again'),
            $headers,
        )->assertCreated();

        $this->assertSame(2, Task::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_retention_cleaner_prunes_expired_idempotency_keys(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        IdempotencyKey::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'key' => 'prune-me',
            'route' => 'POST api/v1/tenants/{tenantId}/environments/{environmentId}/tasks',
            'request_hash' => hash('sha256', 'x'),
            'response_code' => 201,
            'response_body' => ['data' => ['id' => 'task_x']],
            'created_at' => now()->subDays(2),
            'expires_at' => now()->subHour(),
        ]);

        $counts = app(RetentionCleaner::class)->run();

        $this->assertGreaterThanOrEqual(1, $counts['idempotency_keys_deleted']);
        $this->assertSame(0, IdempotencyKey::query()->where('key', 'prune-me')->count());
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
