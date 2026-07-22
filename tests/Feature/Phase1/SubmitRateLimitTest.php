<?php

namespace Tests\Feature\Phase1;

use App\Infrastructure\Persistence\Eloquent\RateLimitBucket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class SubmitRateLimitTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_burst_task_creates_receive_429_with_retry_after(): void
    {
        config([
            'scheduler.submit_rate_limit_per_minute' => 3,
            'scheduler.submit_rate_limit_window_seconds' => 60,
        ]);

        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $route = $this->environmentRoute($tenant, $environment, '/tasks');

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($admin)->postJson($route, $this->draftPayload('Task '.$i))
                ->assertCreated();
        }

        $this->actingAs($admin)->postJson($route, $this->draftPayload('Task overflow'))
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'too_many_requests')
            ->assertHeader('Retry-After');

        $this->assertSame(1, RateLimitBucket::query()->count());
    }

    public function test_workspace_override_limit_is_honored(): void
    {
        config(['scheduler.submit_rate_limit_per_minute' => 100]);

        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $environment->submit_rate_limit_per_minute = 2;
        $environment->save();

        $route = $this->environmentRoute($tenant, $environment, '/tasks');

        $this->actingAs($admin)->postJson($route, $this->draftPayload('A'))->assertCreated();
        $this->actingAs($admin)->postJson($route, $this->draftPayload('B'))->assertCreated();
        $this->actingAs($admin)->postJson($route, $this->draftPayload('C'))
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_rate_limit_is_scoped_per_workspace(): void
    {
        config(['scheduler.submit_rate_limit_per_minute' => 1]);

        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $other = $tenant->environments()->where('slug', 'staging')->firstOrFail();

        $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks'),
            $this->draftPayload('Dev'),
        )->assertCreated();

        $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks'),
            $this->draftPayload('Dev2'),
        )->assertStatus(429);

        $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $other, '/tasks'),
            $this->draftPayload('Staging'),
        )->assertCreated();
    }

    /**
     * @return array<string, mixed>
     */
    private function draftPayload(string $name): array
    {
        return [
            'name' => $name,
            'method' => 'POST',
            'url_or_path' => 'http://receiver:8080/hook',
            'definition_status' => 'draft',
            'schedule' => [
                'kind' => 'daily_at',
                'timezone' => 'UTC',
                'time' => '09:00',
            ],
        ];
    }
}
