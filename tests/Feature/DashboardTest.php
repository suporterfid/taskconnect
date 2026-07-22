<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_dashboard_returns_counts_for_environment(): void
    {
        [$user, $tenant, $environment] = $this->createTenantAdmin();

        $response = $this->actingAs($user)
            ->getJson($this->environmentRoute($tenant, $environment, '/dashboard'));

        $response->assertOk()
            ->assertJsonPath('data.active_tasks', 0)
            ->assertJsonPath('data.failed_tasks', 0)
            ->assertJsonStructure([
                'data' => [
                    'active_tasks',
                    'paused_tasks',
                    'recent_runs',
                    'failed_runs_24h',
                    'failed_tasks',
                    'retry_wait_runs',
                    'dead_runs',
                    'upcoming_tasks',
                    'recent_run_items',
                    'scheduler_last_seen_at',
                ],
            ]);
    }

    public function test_dashboard_counts_failed_tasks_by_last_run_state(): void
    {
        [$user, $tenant, $environment] = $this->createTenantAdmin();

        \App\Infrastructure\Persistence\Eloquent\Task::factory()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'definition_status' => \App\Domain\Execution\Enums\TaskDefinitionStatus::Active,
            'last_run_state' => 'dead',
        ]);

        \App\Infrastructure\Persistence\Eloquent\Task::factory()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'definition_status' => \App\Domain\Execution\Enums\TaskDefinitionStatus::Active,
            'last_run_state' => 'succeeded',
        ]);

        $this->actingAs($user)
            ->getJson($this->environmentRoute($tenant, $environment, '/dashboard'))
            ->assertOk()
            ->assertJsonPath('data.failed_tasks', 1)
            ->assertJsonPath('data.active_tasks', 2);
    }
}
