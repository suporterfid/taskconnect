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

    public function test_index_includes_revoked_keys_with_active_first(): void
    {
        [$admin, $tenant] = $this->createTenantAdmin();

        $active = $this->actingAs($admin)->postJson(
            $this->tenantRoute($tenant, '/api-keys'),
            ['name' => 'Active Key', 'permissions' => ['*']],
        )->json('data.id');

        $revoked = $this->actingAs($admin)->postJson(
            $this->tenantRoute($tenant, '/api-keys'),
            ['name' => 'Revoked Key', 'permissions' => ['endpoint_profiles:read']],
        )->json('data.id');

        $this->actingAs($admin)->deleteJson($this->tenantRoute($tenant, '/api-keys/'.$revoked))
            ->assertNoContent();

        $list = $this->actingAs($admin)->getJson($this->tenantRoute($tenant, '/api-keys'))
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $list);
        $this->assertSame($active, $list[0]['id']);
        $this->assertNull($list[0]['revoked_at']);
        $this->assertSame($revoked, $list[1]['id']);
        $this->assertNotNull($list[1]['revoked_at']);
    }

    public function test_update_changes_name_permissions_and_expiry(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $apiKeyId = $this->actingAs($admin)->postJson(
            $this->tenantRoute($tenant, '/api-keys'),
            [
                'name' => 'Original',
                'permissions' => ['*'],
                'environment_id' => $environment->public_id,
            ],
        )->json('data.id');

        $expiresAt = now()->addMonth()->utc()->toIso8601String();

        $update = $this->actingAs($admin)->patchJson(
            $this->tenantRoute($tenant, '/api-keys/'.$apiKeyId),
            [
                'name' => 'Renamed',
                'permissions' => ['endpoint_profiles:read', 'endpoint_profiles:write', '*'],
                'expires_at' => $expiresAt,
            ],
        );

        $update->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.permissions', ['*'])
            ->assertJsonPath('data.environment_id', $environment->public_id)
            ->assertJsonMissingPath('data.plaintext');

        $this->assertNotNull($update->json('data.expires_at'));
    }

    public function test_update_rejects_revoked_key_and_unknown_permissions(): void
    {
        [$admin, $tenant] = $this->createTenantAdmin();

        $apiKeyId = $this->actingAs($admin)->postJson(
            $this->tenantRoute($tenant, '/api-keys'),
            ['name' => 'Soon Revoked', 'permissions' => ['secrets:manage']],
        )->json('data.id');

        $this->actingAs($admin)->postJson(
            $this->tenantRoute($tenant, '/api-keys'),
            ['name' => 'Bad Perms', 'permissions' => ['not:a:real:permission']],
        )->assertUnprocessable();

        $this->actingAs($admin)->deleteJson($this->tenantRoute($tenant, '/api-keys/'.$apiKeyId))
            ->assertNoContent();

        $this->actingAs($admin)->patchJson(
            $this->tenantRoute($tenant, '/api-keys/'.$apiKeyId),
            ['name' => 'Nope'],
        )->assertUnprocessable();
    }
}
