<?php

namespace Tests\Feature\Phase1;

use App\Domain\Scheduling\ScheduleKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class ArchivedEnvironmentGuardTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_archived_environment_rejects_task_create(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $environment->archived_at = now();
        $environment->save();

        $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks'),
            [
                'name' => 'Should Fail',
                'method' => 'POST',
                'url_or_path' => 'http://receiver:8080/hook',
                'schedule' => [
                    'kind' => ScheduleKind::EveryNMinutes->value,
                    'timezone' => 'UTC',
                    'interval_minutes' => 15,
                ],
            ],
        )->assertStatus(422)
            ->assertJsonValidationErrors(['environment'], 'error.details');
    }
}
