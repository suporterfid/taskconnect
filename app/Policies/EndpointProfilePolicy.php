<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\EndpointProfile;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Policies\Concerns\InteractsWithTenantAccess;
use Illuminate\Contracts\Auth\Authenticatable;

class EndpointProfilePolicy
{
    use InteractsWithTenantAccess;

    public function viewAny(Authenticatable $user, Tenant $tenant): bool
    {
        return $this->actorHasTenantAccess($tenant) || $this->actorCanUseProfiles($tenant);
    }

    public function view(Authenticatable $user, EndpointProfile $profile, Tenant $tenant): bool
    {
        return $profile->tenant_id === $tenant->id && $this->viewAny($user, $tenant);
    }

    public function create(Authenticatable $user, Tenant $tenant): bool
    {
        return $this->actorCanWriteProfiles($tenant);
    }

    public function update(Authenticatable $user, EndpointProfile $profile, Tenant $tenant): bool
    {
        return $profile->tenant_id === $tenant->id && $this->create($user, $tenant);
    }

    public function delete(Authenticatable $user, EndpointProfile $profile, Tenant $tenant): bool
    {
        return $this->update($user, $profile, $tenant);
    }

    public function test(Authenticatable $user, EndpointProfile $profile, Tenant $tenant): bool
    {
        return $this->update($user, $profile, $tenant);
    }

    public function disableTlsVerification(Authenticatable $user, Tenant $tenant): bool
    {
        return $this->actorIsTenantAdmin($tenant);
    }
}
