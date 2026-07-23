<?php

namespace Tests\Feature\Phase1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class SpecV1AliasesTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_default_task_response_omits_spec_v1_aliases(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $taskId = $this->createTask($admin, $tenant, $environment, [
            'body' => '{"file_id":"abc"}',
            'content_type' => 'application/json',
        ]);

        $response = $this->actingAs($admin)->getJson(
            $this->environmentRoute($tenant, $environment, '/tasks/'.$taskId),
        )->assertOk();

        $response->assertJsonMissingPath('data.target_url');
        $response->assertJsonMissingPath('data.payload');
    }

    public function test_spec_v1_aliases_mirror_url_and_json_payload_when_opted_in(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $taskId = $this->createTask($admin, $tenant, $environment, [
            'body' => '{"file_id":"abc"}',
            'content_type' => 'application/json',
        ]);

        $this->actingAs($admin)->getJson(
            $this->environmentRoute($tenant, $environment, '/tasks/'.$taskId.'?aliases=spec-v1'),
        )
            ->assertOk()
            ->assertJsonPath('data.url_or_path', 'https://receiver:8080/hook')
            ->assertJsonPath('data.target_url', 'https://receiver:8080/hook')
            ->assertJsonPath('data.payload.file_id', 'abc');
    }

    public function test_spec_v1_alias_payload_falls_back_to_raw_body_for_non_json_content_type(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $taskId = $this->createTask($admin, $tenant, $environment, [
            'body' => 'file_id=abc',
            'content_type' => 'application/x-www-form-urlencoded',
        ]);

        $this->actingAs($admin)->getJson(
            $this->environmentRoute($tenant, $environment, '/tasks/'.$taskId.'?aliases=spec-v1'),
        )
            ->assertOk()
            ->assertJsonPath('data.payload', 'file_id=abc');
    }

    /**
     * @param  array{body?: string, content_type?: string}  $overrides
     */
    private function createTask($admin, $tenant, $environment, array $overrides): string
    {
        $response = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks'),
            [
                'name' => 'Alias Task',
                'method' => 'POST',
                'url_or_path' => 'https://receiver:8080/hook',
                'body' => $overrides['body'] ?? null,
                'content_type' => $overrides['content_type'] ?? null,
                'schedule' => [
                    'kind' => 'daily_at',
                    'timezone' => 'UTC',
                    'time' => '09:00',
                ],
            ],
        )->assertCreated();

        return $response->json('data.id');
    }
}
