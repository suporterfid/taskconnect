<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\ApiKey;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Policies\Concerns\InteractsWithTenantAccess;
use Illuminate\Contracts\Auth\Authenticatable;

class ApiKeyPolicy
{
    use InteractsWithTenantAccess;

    public function viewAny(Authenticatable $user, Tenant $tenant): bool
    {
        return $this->actorCanManageApiKeys($tenant);
    }

    public function create(Authenticatable $user, Tenant $tenant): bool
    {
        return $this->actorCanManageApiKeys($tenant);
    }

    public function delete(Authenticatable $user, ApiKey $apiKey, Tenant $tenant): bool
    {
        return $apiKey->tenant_id === $tenant->id && $this->actorCanManageApiKeys($tenant);
    }
}
