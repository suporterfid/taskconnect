<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Policies\Concerns\InteractsWithTenantAccess;
use Illuminate\Contracts\Auth\Authenticatable;

class MemberPolicy
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

    public function update(Authenticatable $user, TenantMembership $membership, Tenant $tenant): bool
    {
        if ($membership->tenant_id !== $tenant->id) {
            return false;
        }

        return $this->actorIsTenantAdmin($tenant);
    }

    public function delete(Authenticatable $user, TenantMembership $membership, Tenant $tenant): bool
    {
        return $this->update($user, $membership, $tenant);
    }
}
