<?php

namespace Tests\Feature\Phase1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class SchedulePreviewTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_preview_returns_next_occurrences(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $response = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/schedules/preview'),
            [
                'schedule' => [
                    'kind' => 'every_n_minutes',
                    'timezone' => 'UTC',
                    'interval_minutes' => 15,
                    'starts_at' => '2026-07-18T10:00:00Z',
                ],
                'count' => 3,
            ]
        );

        $response->assertOk()
            ->assertJsonStructure(['data' => ['occurrences']])
            ->assertJsonCount(3, 'data.occurrences');
    }

    public function test_preview_rejects_invalid_schedule(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $response = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/schedules/preview'),
            [
                'schedule' => [
                    'kind' => 'every_n_minutes',
                    'timezone' => 'UTC',
                ],
            ]
        );

        $response->assertStatus(422);
    }
}
