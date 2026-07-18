<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\Secret;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Policies\Concerns\InteractsWithTenantAccess;
use Illuminate\Contracts\Auth\Authenticatable;

class SecretPolicy
{
    use InteractsWithTenantAccess;

    public function viewAny(Authenticatable $user, Tenant $tenant): bool
    {
        return $this->actorCanManageSecrets($tenant);
    }

    public function view(Authenticatable $user, Secret $secret, Tenant $tenant): bool
    {
        return $secret->tenant_id === $tenant->id && $this->actorCanManageSecrets($tenant);
    }

    public function create(Authenticatable $user, Tenant $tenant): bool
    {
        return $this->actorCanManageSecrets($tenant);
    }

    public function rotate(Authenticatable $user, Secret $secret, Tenant $tenant): bool
    {
        return $this->view($user, $secret, $tenant);
    }

    public function delete(Authenticatable $user, Secret $secret, Tenant $tenant): bool
    {
        return $this->view($user, $secret, $tenant);
    }
}
