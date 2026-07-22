<?php

namespace Tests\Feature\Phase1;

use App\Infrastructure\Persistence\Eloquent\Secret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class SecretFeatureTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_secret_is_encrypted_at_rest_and_not_returned_on_get(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $create = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/secrets'),
            [
                'name' => 'Webhook Token',
                'value' => 'plaintext-secret-value',
            ],
        );

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Webhook Token')
            ->assertJsonPath('data.plaintext', 'plaintext-secret-value');

        $secretId = $create->json('data.id');

        $stored = Secret::query()->where('public_id', $secretId)->firstOrFail();
        $this->assertNotSame('plaintext-secret-value', $stored->encrypted_payload);
        $this->assertSame('plaintext-secret-value', Crypt::decryptString($stored->encrypted_payload));

        $this->actingAs($admin)->getJson(
            $this->environmentRoute($tenant, $environment, '/secrets/'.$secretId),
        )
            ->assertOk()
            ->assertJsonMissingPath('data.plaintext')
            ->assertJsonMissing(['plaintext' => 'plaintext-secret-value']);
    }

    public function test_tenant_member_cannot_manage_secrets(): void
    {
        [$member, $tenant, $environment] = $this->createTenantAdmin('Member Tenant', \App\Domain\Shared\Enums\TenantRole::TenantMember);

        $this->actingAs($member)->postJson(
            $this->environmentRoute($tenant, $environment, '/secrets'),
            ['name' => 'Denied', 'value' => 'nope'],
        )->assertForbidden();
    }

    public function test_secret_list_includes_usage_count(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $create = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/secrets'),
            [
                'name' => 'Shared Token',
                'value' => 'token-value',
            ],
        )->assertCreated();

        $secretId = $create->json('data.id');

        $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/endpoint-profiles'),
            [
                'name' => 'Uses Secret',
                'base_url' => 'https://example.com/hook',
                'method' => 'POST',
                'auth_mode' => 'bearer',
                'secret_id' => $secretId,
            ],
        )->assertCreated();

        $this->actingAs($admin)->getJson(
            $this->environmentRoute($tenant, $environment, '/secrets'),
        )
            ->assertOk()
            ->assertJsonPath('data.0.usage_count', 1);
    }
}
