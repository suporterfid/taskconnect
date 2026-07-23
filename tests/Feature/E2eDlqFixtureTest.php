<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class E2eDlqFixtureTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_seed_endpoint_creates_a_dead_run_visible_in_dlq(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $seed = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/e2e/dlq-fixture'),
        );

        $seed->assertCreated();
        $runId = $seed->json('data.run_id');
        $this->assertNotEmpty($runId);

        $this->actingAs($admin)->getJson(
            $this->environmentRoute($tenant, $environment, '/dlq'),
        )
            ->assertOk()
            ->assertJsonPath('data.0.id', $runId)
            ->assertJsonPath('data.0.run_state', 'dead');
    }
}
