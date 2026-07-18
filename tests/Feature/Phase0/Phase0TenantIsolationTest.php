<?php

namespace Tests\Feature\Phase0;

use App\Application\Tenancy\TenantProvisioner;
use App\Domain\Shared\Enums\TenantRole;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase0TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_another_tenants_resources(): void
    {
        [$userA, $tenantA] = $this->createTenantMember('Tenant A');
        [, $tenantB] = $this->createTenantMember('Tenant B');

        $this->actingAs($userA)
            ->getJson('/api/v1/tenants/'.$tenantB->public_id)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_environment_must_belong_to_tenant_in_route(): void
    {
        [$userA, $tenantA] = $this->createTenantMember('Tenant A');
        [, $tenantB] = $this->createTenantMember('Tenant B');

        $environmentB = $tenantB->environments()->firstOrFail();

        $this->actingAs($userA)
            ->patchJson('/api/v1/tenants/'.$tenantA->public_id.'/environments/'.$environmentB->public_id, [
                'name' => 'Hijacked',
            ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_cross_tenant_environment_substitution_returns_not_found(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $provisioner = app(TenantProvisioner::class);

        $tenantA = $provisioner->create('Tenant A');
        $tenantB = $provisioner->create('Tenant B');

        $environmentB = $tenantB->environments()->where('slug', 'production')->firstOrFail();

        $this->actingAs($admin)
            ->deleteJson('/api/v1/tenants/'.$tenantA->public_id.'/environments/'.$environmentB->public_id)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_tenant_creation_provisions_default_environments(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/tenants', [
            'name' => 'Acme Corp',
        ]);

        $response->assertCreated();

        $tenant = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            ['development', 'staging', 'production'],
            $tenant->environments()->pluck('slug')->all(),
        );
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function createTenantMember(string $tenantName): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['name' => $tenantName]);

        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => TenantRole::TenantAdmin,
        ]);

        foreach (['development', 'staging', 'production'] as $slug) {
            Environment::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => ucfirst($slug),
                'slug' => $slug,
            ]);
        }

        return [$user, $tenant->fresh(['environments'])];
    }
}
