<?php

namespace Tests\Feature\Phase1;

use App\Application\GrandpaSson\IntrospectionClientInterface;
use App\Application\GrandpaSson\IntrospectionResult;
use App\Domain\Shared\Enums\TenantRole;
use App\Infrastructure\Persistence\Eloquent\AuditLog;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeGrandpaSsonIntrospectionClient;
use Tests\TestCase;

class GrandpaSsonInboundAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_with_wrong_aud_is_rejected_and_audited(): void
    {
        config([
            'grandpasson.inbound_enabled' => true,
            'grandpasson.write_scope' => 'tasks:write',
        ]);

        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $environment = Environment::factory()->create(['tenant_id' => $tenant->id]);
        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => TenantRole::TenantAdmin,
        ]);

        $fake = (new FakeGrandpaSsonIntrospectionClient)->withToken('opaque-bad-aud', new IntrospectionResult(
            active: true,
            scopes: ['tasks:write'],
            audiences: ['wsp_other'],
        ));
        $this->app->instance(IntrospectionClientInterface::class, $fake);

        $response = $this->withHeader('Authorization', 'Bearer opaque-bad-aud')
            ->withHeader('Idempotency-Key', 'idem-gss-1')
            ->postJson("/api/v1/tenants/{$tenant->public_id}/environments/{$environment->public_id}/tasks", [
                'name' => 'from-gss',
                'method' => 'POST',
                'url_or_path' => 'http://receiver/hook',
                'schedule' => [
                    'kind' => 'every_n_minutes',
                    'timezone' => 'UTC',
                    'interval_minutes' => 15,
                ],
            ]);

        $response->assertForbidden();
        $this->assertTrue(
            AuditLog::query()->where('action', 'grandpasson.workspace_denied')->exists(),
        );
    }

    public function test_token_with_matching_aud_can_create_task(): void
    {
        config([
            'grandpasson.inbound_enabled' => true,
            'grandpasson.write_scope' => 'tasks:write',
        ]);

        $tenant = Tenant::factory()->create();
        $environment = Environment::factory()->create(['tenant_id' => $tenant->id]);

        $fake = (new FakeGrandpaSsonIntrospectionClient)->withToken('opaque-ok', new IntrospectionResult(
            active: true,
            scopes: ['tasks:write'],
            audiences: [$environment->public_id],
        ));
        $this->app->instance(IntrospectionClientInterface::class, $fake);

        $response = $this->withHeader('Authorization', 'Bearer opaque-ok')
            ->withHeader('Idempotency-Key', 'idem-gss-2')
            ->postJson("/api/v1/tenants/{$tenant->public_id}/environments/{$environment->public_id}/tasks", [
                'name' => 'from-gss-ok',
                'method' => 'POST',
                'url_or_path' => 'http://receiver/hook',
                'schedule' => [
                    'kind' => 'every_n_minutes',
                    'timezone' => 'UTC',
                    'interval_minutes' => 15,
                ],
            ]);

        $response->assertCreated();
    }

    public function test_token_with_workspace_prefixed_aud_can_create_task(): void
    {
        config([
            'grandpasson.inbound_enabled' => true,
            'grandpasson.write_scope' => 'tasks:write',
        ]);

        $tenant = Tenant::factory()->create();
        $environment = Environment::factory()->create(['tenant_id' => $tenant->id]);

        $fake = (new FakeGrandpaSsonIntrospectionClient)->withToken('opaque-prefixed', new IntrospectionResult(
            active: true,
            scopes: ['tasks:write'],
            audiences: ['workspace/'.$environment->public_id],
        ));
        $this->app->instance(IntrospectionClientInterface::class, $fake);

        $response = $this->withHeader('Authorization', 'Bearer opaque-prefixed')
            ->withHeader('Idempotency-Key', 'idem-gss-3')
            ->postJson("/api/v1/tenants/{$tenant->public_id}/environments/{$environment->public_id}/tasks", [
                'name' => 'from-gss-prefixed',
                'method' => 'POST',
                'url_or_path' => 'http://receiver/hook',
                'schedule' => [
                    'kind' => 'every_n_minutes',
                    'timezone' => 'UTC',
                    'interval_minutes' => 15,
                ],
            ]);

        $response->assertCreated();
    }
}
