<?php

namespace Tests\Feature\Phase1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class Phase1TenantIsolationTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_user_cannot_read_another_tenants_secrets(): void
    {
        [$userA, $tenantA, $environmentA] = $this->createTenantAdmin('Tenant A');
        [$userB, $tenantB, $environmentB] = $this->createTenantAdmin('Tenant B');

        $secret = $this->actingAs($userB)->postJson(
            $this->environmentRoute($tenantB, $environmentB, '/secrets'),
            ['name' => 'Private', 'value' => 'hidden'],
        );

        $secretId = $secret->json('data.id');

        $this->actingAs($userA)->getJson(
            '/api/v1/tenants/'.$tenantA->public_id.'/environments/'.$environmentB->public_id.'/secrets/'.$secretId,
        )->assertNotFound();

        $this->actingAs($userA)->getJson(
            $this->environmentRoute($tenantA, $environmentA, '/secrets/'.$secretId),
        )->assertNotFound();
    }

    public function test_user_cannot_revoke_another_tenants_api_key(): void
    {
        [$userA, $tenantA] = $this->createTenantAdmin('Tenant A');
        [$userB, $tenantB] = $this->createTenantAdmin('Tenant B');

        $apiKeyId = $this->actingAs($userB)->postJson(
            $this->tenantRoute($tenantB, '/api-keys'),
            ['name' => 'B Key', 'permissions' => ['*']],
        )->json('data.id');

        $this->actingAs($userA)->deleteJson(
            $this->tenantRoute($tenantA, '/api-keys/'.$apiKeyId),
        )->assertNotFound();
    }
}
