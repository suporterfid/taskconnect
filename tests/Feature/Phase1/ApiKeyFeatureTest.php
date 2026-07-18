<?php

namespace Tests\Feature\Phase1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class ApiKeyFeatureTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_api_key_plaintext_is_shown_once_and_revocation_blocks_auth(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $create = $this->actingAs($admin)->postJson(
            $this->tenantRoute($tenant, '/api-keys'),
            [
                'name' => 'Automation',
                'permissions' => ['*'],
            ],
        );

        $create->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'plaintext', 'key_prefix']]);

        $plaintext = $create->json('data.plaintext');
        $apiKeyId = $create->json('data.id');

        $this->assertStringStartsWith('tc_', $plaintext);

        $this->actingAs($admin)->getJson($this->tenantRoute($tenant, '/api-keys'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.plaintext');

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson($this->tenantRoute($tenant, '/environments'))
            ->assertOk();

        $this->actingAs($admin)->deleteJson($this->tenantRoute($tenant, '/api-keys/'.$apiKeyId))
            ->assertNoContent();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson($this->tenantRoute($tenant, '/environments'))
            ->assertUnauthorized();
    }
}
