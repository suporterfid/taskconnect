<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\User;

class TaskRunPolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $this->hasTenantAccess($user, $tenant);
    }

    public function view(User $user, TaskRun $run, Tenant $tenant): bool
    {
        return $run->tenant_id === $tenant->id && $this->hasTenantAccess($user, $tenant);
    }

    public function cancel(User $user, TaskRun $run, Tenant $tenant): bool
    {
        return $this->view($user, $run, $tenant) && $this->canManageTasks($user, $tenant);
    }

    public function retry(User $user, TaskRun $run, Tenant $tenant): bool
    {
        return $this->cancel($user, $run, $tenant);
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

        return $user->memberships()->where('tenant_id', $tenant->id)->exists();
    }
}
