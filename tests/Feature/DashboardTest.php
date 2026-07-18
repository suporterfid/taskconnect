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
            ->assertJsonStructure([
                'data' => [
                    'active_tasks',
                    'paused_tasks',
                    'recent_runs',
                    'failed_runs_24h',
                    'scheduler_last_seen_at',
                ],
            ]);
    }
}
