<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Policies\Concerns\InteractsWithTenantAccess;
use Illuminate\Contracts\Auth\Authenticatable;

class EnvironmentPolicy
{
    use InteractsWithTenantAccess;

    public function viewAny(Authenticatable $user, Tenant $tenant): bool
    {
        return $this->actorHasTenantAccess($tenant);
    }

    public function create(Authenticatable $user, Tenant $tenant): bool
    {
        return $this->actorIsTenantAdmin($tenant);
    }

    public function update(Authenticatable $user, Environment $environment, Tenant $tenant): bool
    {
        if ($environment->tenant_id !== $tenant->id) {
            return false;
        }

        return $this->actorIsTenantAdmin($tenant);
    }

    public function delete(Authenticatable $user, Environment $environment, Tenant $tenant): bool
    {
        return $this->update($user, $environment, $tenant);
    }
}
