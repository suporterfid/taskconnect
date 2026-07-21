<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Policies\Concerns\InteractsWithTenantAccess;
use Illuminate\Contracts\Auth\Authenticatable;

class TaskPolicy
{
    use InteractsWithTenantAccess;

    public function viewAny(Authenticatable $user, Tenant $tenant): bool
    {
        return $this->actorCanReadTasks($tenant);
    }

    public function view(Authenticatable $user, Task $task, Tenant $tenant): bool
    {
        return $task->tenant_id === $tenant->id && $this->actorCanReadTasks($tenant);
    }

    public function create(Authenticatable $user, Tenant $tenant): bool
    {
        return $this->actorCanWriteTasks($tenant);
    }

    public function update(Authenticatable $user, Task $task, Tenant $tenant): bool
    {
        return $task->tenant_id === $tenant->id && $this->actorCanWriteTasks($tenant);
    }

    public function delete(Authenticatable $user, Task $task, Tenant $tenant): bool
    {
        return $this->update($user, $task, $tenant);
    }

    public function operate(Authenticatable $user, Task $task, Tenant $tenant): bool
    {
        return $task->tenant_id === $tenant->id && $this->actorCanOperateTasks($tenant);
    }
}
