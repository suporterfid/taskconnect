<?php

namespace Tests\Support;

use App\Domain\Shared\Enums\TenantRole;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;

trait CreatesTenantFixtures
{
    /**
     * @return array{0: User, 1: Tenant, 2: Environment}
     */
    protected function createTenantAdmin(string $tenantName = 'Test Tenant', TenantRole $role = TenantRole::TenantAdmin): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['name' => $tenantName]);

        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        foreach (['development', 'staging', 'production'] as $slug) {
            Environment::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => ucfirst($slug),
                'slug' => $slug,
            ]);
        }

        $tenant = $tenant->fresh(['environments']);
        $environment = $tenant->environments()->where('slug', 'development')->firstOrFail();

        return [$user, $tenant, $environment];
    }

    protected function environmentRoute(Tenant $tenant, Environment $environment, string $suffix): string
    {
        return '/api/v1/tenants/'.$tenant->public_id.'/environments/'.$environment->public_id.$suffix;
    }

    protected function tenantRoute(Tenant $tenant, string $suffix): string
    {
        return '/api/v1/tenants/'.$tenant->public_id.$suffix;
    }
}
