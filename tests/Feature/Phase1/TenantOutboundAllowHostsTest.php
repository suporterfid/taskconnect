<?php

namespace Tests\Feature\Phase1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class TenantOutboundAllowHostsTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_tenant_admin_can_patch_outbound_allow_hosts(): void
    {
        [$admin, $tenant] = $this->createTenantAdmin();

        $response = $this->actingAs($admin)->patchJson(
            $this->tenantRoute($tenant, ''),
            [
                'outbound_allow_hosts' => ['Webhook.Partner.Example', 'api.example.com'],
            ],
        );

        $response->assertOk()
            ->assertJsonPath('data.outbound_allow_hosts', ['webhook.partner.example', 'api.example.com']);

        $this->assertSame(
            ['webhook.partner.example', 'api.example.com'],
            $tenant->fresh()->outbound_allow_hosts,
        );
    }

    public function test_outbound_allow_hosts_rejects_invalid_hostnames(): void
    {
        [$admin, $tenant] = $this->createTenantAdmin();

        $this->actingAs($admin)->patchJson(
            $this->tenantRoute($tenant, ''),
            [
                'outbound_allow_hosts' => ['not a host'],
            ],
        )->assertStatus(422);
    }

    public function test_tenant_member_cannot_patch_outbound_allow_hosts(): void
    {
        [$member, $tenant] = $this->createTenantAdmin(
            tenantName: 'Member Tenant',
            role: \App\Domain\Shared\Enums\TenantRole::TenantMember,
        );

        $this->actingAs($member)->patchJson(
            $this->tenantRoute($tenant, ''),
            [
                'outbound_allow_hosts' => ['api.example.com'],
            ],
        )->assertForbidden();
    }
}
