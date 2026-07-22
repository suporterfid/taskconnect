<?php

namespace Tests\Feature\Phase1;

use App\Infrastructure\Persistence\Eloquent\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class TaskCoalesceTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_many_submits_with_same_coalesce_key_within_window_reuse_one_task(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $route = $this->environmentRoute($tenant, $environment, '/tasks');

        $first = $this->actingAs($admin)->postJson($route, $this->publishPayload('pub-1'));
        $first->assertCreated()
            ->assertJsonPath('meta.coalesced', false)
            ->assertJsonPath('data.coalesce_key', 'publish:workspace');

        $second = $this->actingAs($admin)->postJson($route, $this->publishPayload('pub-2'));
        $second->assertOk()
            ->assertJsonPath('meta.coalesced', true)
            ->assertJsonPath('data.id', $first->json('data.id'));

        $third = $this->actingAs($admin)->postJson($route, $this->publishPayload('pub-3'));
        $third->assertOk()
            ->assertJsonPath('meta.coalesced', true)
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(1, Task::query()
            ->where('environment_id', $environment->id)
            ->where('coalesce_key', 'publish:workspace')
            ->count());
    }

    public function test_coalesce_is_scoped_per_workspace(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $other = $tenant->environments()->where('slug', 'staging')->firstOrFail();

        $a = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks'),
            $this->publishPayload('a'),
        )->assertCreated();

        $b = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $other, '/tasks'),
            $this->publishPayload('b'),
        )->assertCreated();

        $this->assertNotSame($a->json('data.id'), $b->json('data.id'));
    }

    public function test_different_coalesce_keys_create_separate_tasks(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $route = $this->environmentRoute($tenant, $environment, '/tasks');

        $a = $this->actingAs($admin)->postJson($route, $this->publishPayload('a', 'key-a'))->assertCreated();
        $b = $this->actingAs($admin)->postJson($route, $this->publishPayload('b', 'key-b'))->assertCreated();

        $this->assertNotSame($a->json('data.id'), $b->json('data.id'));
    }

    public function test_after_window_expires_a_new_task_is_created(): void
    {
        config()->set('scheduler.coalesce_window_seconds', 30);
        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $route = $this->environmentRoute($tenant, $environment, '/tasks');

        $first = $this->actingAs($admin)->postJson($route, $this->publishPayload('first'))->assertCreated();
        $task = Task::query()->where('public_id', $first->json('data.id'))->firstOrFail();
        $task->created_at = now()->subSeconds(90);
        $task->save();

        $second = $this->actingAs($admin)->postJson($route, $this->publishPayload('second'))->assertCreated();
        $this->assertNotSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(2, Task::query()
            ->where('environment_id', $environment->id)
            ->where('coalesce_key', 'publish:workspace')
            ->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function publishPayload(string $nameSuffix, string $coalesceKey = 'publish:workspace'): array
    {
        return [
            'name' => 'Publish '.$nameSuffix,
            'method' => 'POST',
            'url_or_path' => 'http://receiver:8080/publish',
            'task_type' => 'publish.build',
            'coalesce_key' => $coalesceKey,
            'definition_status' => 'draft',
            'schedule' => [
                'kind' => 'daily_at',
                'timezone' => 'UTC',
                'time' => '09:00',
            ],
        ];
    }
}
