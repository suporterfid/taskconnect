<?php

namespace Tests\Feature\Phase1;

use App\Infrastructure\Persistence\Eloquent\EndpointProfile;
use App\Infrastructure\Persistence\Eloquent\Secret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ArrayDnsResolver;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class EndpointProfileFeatureTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['outbound.allow_http' => true]);

        $this->app->instance(
            \App\Domain\Execution\Outbound\DnsResolverInterface::class,
            new ArrayDnsResolver([
                'receiver' => ['127.0.0.1'],
                'example.com' => ['93.184.216.34'],
            ]),
        );

        $this->app->forgetInstance(\App\Domain\Execution\Outbound\OutboundPolicy::class);
        $this->app->forgetInstance(\App\Infrastructure\HttpClient\PinnedHttpTransport::class);
    }

    public function test_endpoint_profile_test_blocks_private_ip_without_allowlist(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $profile = $this->createProfile($admin, $tenant, $environment, 'https://192.168.0.10/hook');

        $response = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/endpoint-profiles/'.$profile->public_id.'/test'),
        );

        $response->assertOk()
            ->assertJsonPath('data.transport_error_code', 'host_not_allowlisted')
            ->assertJsonPath('data.response_status', null);
    }

    public function test_endpoint_profile_test_allows_testing_allowlist_host(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $profile = $this->createProfile($admin, $tenant, $environment, 'http://receiver/hook');

        $response = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/endpoint-profiles/'.$profile->public_id.'/test'),
        );

        $response->assertOk();

        $this->assertNotSame('blocked_ip', $response->json('data.transport_error_code'));
    }

    public function test_endpoint_profile_test_allows_tenant_outbound_allow_hosts(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $tenant->outbound_allow_hosts = ['private.partner.example'];
        $tenant->save();

        $this->app->instance(
            \App\Domain\Execution\Outbound\DnsResolverInterface::class,
            new ArrayDnsResolver([
                'receiver' => ['127.0.0.1'],
                'example.com' => ['93.184.216.34'],
                'private.partner.example' => ['10.0.0.8'],
            ]),
        );
        $this->app->forgetInstance(\App\Domain\Execution\Outbound\OutboundPolicy::class);
        $this->app->forgetInstance(\App\Infrastructure\HttpClient\PinnedHttpTransport::class);

        config([
            'outbound.allow_http' => true,
            'outbound.testing_allow_hosts' => [],
        ]);

        $profile = $this->createProfile($admin, $tenant, $environment, 'https://private.partner.example/hook');

        $response = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/endpoint-profiles/'.$profile->public_id.'/test'),
        );

        $response->assertOk();
        $this->assertNotSame('blocked_ip', $response->json('data.transport_error_code'));
    }

    public function test_cross_tenant_secret_reference_is_rejected(): void
    {
        [$adminA, $tenantA, $environmentA] = $this->createTenantAdmin('Tenant A');
        [$adminB, $tenantB, $environmentB] = $this->createTenantAdmin('Tenant B');

        $foreignSecret = $this->actingAs($adminB)->postJson(
            $this->environmentRoute($tenantB, $environmentB, '/secrets'),
            ['name' => 'Foreign', 'value' => 'secret'],
        )->json('data.id');

        $this->actingAs($adminA)->postJson(
            $this->environmentRoute($tenantA, $environmentA, '/endpoint-profiles'),
            [
                'name' => 'Bad Profile',
                'base_url' => 'https://example.com/hook',
                'auth_mode' => 'bearer',
                'secret_id' => $foreignSecret,
            ],
        )->assertUnprocessable();
    }

    public function test_member_can_create_profile_but_not_disable_tls(): void
    {
        [$member, $tenant, $environment] = $this->createTenantAdmin('Members', \App\Domain\Shared\Enums\TenantRole::TenantMember);

        $this->actingAs($member)->postJson(
            $this->environmentRoute($tenant, $environment, '/endpoint-profiles'),
            [
                'name' => 'Member Profile',
                'base_url' => 'https://example.com/hook',
            ],
        )->assertCreated();

        $this->actingAs($member)->postJson(
            $this->environmentRoute($tenant, $environment, '/endpoint-profiles'),
            [
                'name' => 'Insecure Profile',
                'base_url' => 'https://example.com/hook',
                'verify_tls' => false,
            ],
        )->assertForbidden();
    }

    private function createProfile($admin, $tenant, $environment, string $baseUrl): EndpointProfile
    {
        $response = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/endpoint-profiles'),
            [
                'name' => 'Test Profile '.md5($baseUrl),
                'base_url' => $baseUrl,
                'method' => 'GET',
            ],
        );

        $response->assertCreated();

        return EndpointProfile::query()->where('public_id', $response->json('data.id'))->firstOrFail();
    }
}
