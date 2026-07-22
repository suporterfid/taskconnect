<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\PipelineInstance;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Policies\Concerns\InteractsWithTenantAccess;
use Illuminate\Contracts\Auth\Authenticatable;

class PipelineInstancePolicy
{
    use InteractsWithTenantAccess;

    public function viewAny(Authenticatable $user, Tenant $tenant): bool
    {
        return $this->actorCanReadTasks($tenant);
    }

    public function view(Authenticatable $user, PipelineInstance $instance, Tenant $tenant): bool
    {
        return $instance->tenant_id === $tenant->id && $this->actorCanReadTasks($tenant);
    }

    public function create(Authenticatable $user, Tenant $tenant): bool
    {
        return $this->actorCanWriteTasks($tenant);
    }
}
