<?php

namespace Tests\Feature\Phase1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class EnvironmentDlqAlertSettingsTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_admin_can_configure_workspace_dlq_alerts(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $response = $this->actingAs($admin)->patchJson(
            $this->environmentRoute($tenant, $environment, ''),
            [
                'notifications' => [
                    'dead_run_email_enabled' => false,
                    'dead_run_webhook_enabled' => true,
                    'dead_run_webhook_url' => 'https://example.com/hooks/dlq',
                ],
            ],
        );

        $response->assertOk()
            ->assertJsonPath('data.notifications.dead_run_email_enabled', false)
            ->assertJsonPath('data.notifications.dead_run_webhook_enabled', true)
            ->assertJsonPath('data.notifications.dead_run_webhook_url', 'https://example.com/hooks/dlq');
    }

    public function test_private_webhook_url_is_rejected(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $this->actingAs($admin)->patchJson(
            $this->environmentRoute($tenant, $environment, ''),
            [
                'notifications' => [
                    'dead_run_webhook_enabled' => true,
                    'dead_run_webhook_url' => 'http://127.0.0.1/hook',
                ],
            ],
        )->assertStatus(422);
    }
}
