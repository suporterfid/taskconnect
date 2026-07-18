<?php

namespace App\Policies;

use App\Domain\Shared\Enums\TenantRole;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\User;

class TaskPolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $this->hasTenantAccess($user, $tenant);
    }

    public function view(User $user, Task $task, Tenant $tenant): bool
    {
        return $task->tenant_id === $tenant->id && $this->hasTenantAccess($user, $tenant);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return $this->canManageTasks($user, $tenant);
    }

    public function update(User $user, Task $task, Tenant $tenant): bool
    {
        return $task->tenant_id === $tenant->id && $this->canManageTasks($user, $tenant);
    }

    public function delete(User $user, Task $task, Tenant $tenant): bool
    {
        return $this->update($user, $task, $tenant);
    }

    public function operate(User $user, Task $task, Tenant $tenant): bool
    {
        return $this->update($user, $task, $tenant);
    }

    private function hasTenantAccess(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->memberships()->where('tenant_id', $tenant->id)->exists();
    }

    private function canManageTasks(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $membership = $user->membershipFor($tenant);

        return in_array($membership?->role, [TenantRole::TenantAdmin, TenantRole::TenantMember], true);
    }
}
