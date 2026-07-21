<?php

namespace Tests\Feature\Phase1;

use App\Domain\Execution\Enums\TaskDefinitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class TaskFeatureTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_task_lifecycle_create_activate_pause_resume_run_now_duplicate_and_archive(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $create = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks'),
            $this->draftTaskPayload('Daily Hook'),
        );

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Daily Hook')
            ->assertJsonPath('data.definition_status', TaskDefinitionStatus::Draft->value)
            ->assertJsonPath('data.method', 'POST')
            ->assertJsonPath('data.schedule.kind', 'daily_at')
            ->assertJsonPath('data.schedule.timezone', 'UTC')
            ->assertJsonPath('data.schedule.time', '09:30')
            ->assertJsonPath('data.next_run_at', null);

        $taskId = $create->json('data.id');
        $base = $this->environmentRoute($tenant, $environment, '/tasks/'.$taskId);

        $activate = $this->actingAs($admin)->postJson($base.'/activate');
        $activate->assertOk()
            ->assertJsonPath('data.definition_status', TaskDefinitionStatus::Active->value);
        $this->assertNotNull($activate->json('data.next_run_at'));

        $this->actingAs($admin)->postJson($base.'/pause')
            ->assertOk()
            ->assertJsonPath('data.definition_status', TaskDefinitionStatus::Paused->value);

        $resume = $this->actingAs($admin)->postJson($base.'/resume');
        $resume->assertOk()
            ->assertJsonPath('data.definition_status', TaskDefinitionStatus::Active->value);
        $this->assertNotNull($resume->json('data.next_run_at'));

        $this->actingAs($admin)->postJson($base.'/run-now')
            ->assertStatus(202)
            ->assertJsonPath('data.run_state', 'pending')
            ->assertJsonPath('data.task_id', $taskId);

        $duplicate = $this->actingAs($admin)->postJson($base.'/duplicate');
        $duplicate->assertCreated()
            ->assertJsonPath('data.definition_status', TaskDefinitionStatus::Draft->value)
            ->assertJsonPath('data.name', 'Daily Hook (copy)');
        $this->assertNotSame($taskId, $duplicate->json('data.id'));

        $this->actingAs($admin)->deleteJson($base)
            ->assertNoContent();
    }

    public function test_cross_tenant_task_show_returns_not_found(): void
    {
        [$adminA, $tenantA, $environmentA] = $this->createTenantAdmin('Tenant A');
        [$adminB, $tenantB, $environmentB] = $this->createTenantAdmin('Tenant B');

        $taskId = $this->actingAs($adminB)->postJson(
            $this->environmentRoute($tenantB, $environmentB, '/tasks'),
            $this->draftTaskPayload('Private Task'),
        )->json('data.id');

        $this->actingAs($adminA)->getJson(
            $this->environmentRoute($tenantA, $environmentA, '/tasks/'.$taskId),
        )->assertNotFound();
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
