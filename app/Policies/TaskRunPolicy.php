<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Policies\Concerns\InteractsWithTenantAccess;
use Illuminate\Contracts\Auth\Authenticatable;

class TaskRunPolicy
{
    use InteractsWithTenantAccess;

    public function viewAny(Authenticatable $user, Tenant $tenant): bool
    {
        return $this->actorCanReadTasks($tenant);
    }

    public function view(Authenticatable $user, TaskRun $run, Tenant $tenant): bool
    {
        return $run->tenant_id === $tenant->id && $this->actorCanReadTasks($tenant);
    }

    public function cancel(Authenticatable $user, TaskRun $run, Tenant $tenant): bool
    {
        return $run->tenant_id === $tenant->id && $this->actorCanOperateTasks($tenant);
    }

    public function retry(Authenticatable $user, TaskRun $run, Tenant $tenant): bool
    {
        return $this->cancel($user, $run, $tenant);
    }
}
