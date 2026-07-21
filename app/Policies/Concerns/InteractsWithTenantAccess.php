<?php

namespace App\Policies\Concerns;

use App\Auth\ApiKeyActor;
use App\Domain\Shared\Enums\TenantRole;
use App\Infrastructure\Persistence\Eloquent\ApiKey;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

trait InteractsWithTenantAccess
{
    protected function hasTenantAccess(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->memberships()->where('tenant_id', $tenant->id)->exists();
    }

    protected function isTenantAdmin(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $membership = $user->membershipFor($tenant);

        return $membership?->role === TenantRole::TenantAdmin;
    }

    protected function isTenantMember(User $user, Tenant $tenant): bool
    {
        if ($this->isTenantAdmin($user, $tenant)) {
            return true;
        }

        $membership = $user->membershipFor($tenant);

        return in_array($membership?->role, [TenantRole::TenantMember, TenantRole::ReadOnlyViewer], true);
    }

    protected function canWriteProfiles(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $membership = $user->membershipFor($tenant);

        return in_array($membership?->role, [TenantRole::TenantAdmin, TenantRole::TenantMember], true);
    }

    protected function apiKeyFromRequest(?Request $request = null): ?ApiKey
    {
        $request ??= request();
        $actor = $request?->user();

        if ($actor instanceof ApiKeyActor) {
            return $actor->apiKey;
        }

        /** @var ApiKey|null $apiKey */
        $apiKey = $request?->attributes->get('api_key');

        return $apiKey;
    }

    protected function actorUser(?Authenticatable $user): ?User
    {
        return $user instanceof User ? $user : null;
    }

    protected function apiKeyHasPermission(string $permission): bool
    {
        $apiKey = $this->apiKeyFromRequest();

        return $apiKey?->hasPermission($permission) ?? false;
    }

    protected function actorIsTenantAdmin(Tenant $tenant): bool
    {
        $user = request()->user();

        if ($user instanceof User && $this->isTenantAdmin($user, $tenant)) {
            return true;
        }

        return $this->apiKeyHasPermission('*') || $this->apiKeyHasPermission('tenant:admin');
    }

    protected function actorHasTenantAccess(Tenant $tenant): bool
    {
        $user = request()->user();

        if ($user instanceof User && $this->hasTenantAccess($user, $tenant)) {
            return true;
        }

        return $this->apiKeyFromRequest() !== null;
    }

    protected function actorCanWriteProfiles(Tenant $tenant): bool
    {
        $user = request()->user();

        if ($user instanceof User && $this->canWriteProfiles($user, $tenant)) {
            return true;
        }

        return $this->apiKeyHasPermission('*') || $this->apiKeyHasPermission('endpoint_profiles:write');
    }

    protected function actorCanManageSecrets(Tenant $tenant): bool
    {
        $user = request()->user();

        if ($user instanceof User && $this->isTenantAdmin($user, $tenant)) {
            return true;
        }

        return $this->apiKeyHasPermission('*') || $this->apiKeyHasPermission('secrets:manage');
    }

    protected function actorCanManageApiKeys(Tenant $tenant): bool
    {
        return $this->actorCanManageSecrets($tenant)
            || $this->apiKeyHasPermission('api_keys:manage');
    }

    protected function actorCanUseProfiles(Tenant $tenant): bool
    {
        $user = request()->user();

        if ($user instanceof User && $this->canWriteProfiles($user, $tenant)) {
            return true;
        }

        return $this->apiKeyHasPermission('*')
            || $this->apiKeyHasPermission('endpoint_profiles:read')
            || $this->apiKeyHasPermission('endpoint_profiles:write');
    }

    protected function canWriteTasks(User $user, Tenant $tenant): bool
    {
        return $this->canWriteProfiles($user, $tenant);
    }

    protected function actorCanReadTasks(Tenant $tenant): bool
    {
        $user = request()->user();

        if ($user instanceof User && $this->hasTenantAccess($user, $tenant)) {
            return true;
        }

        return $this->apiKeyHasPermission('*')
            || $this->apiKeyHasPermission('tasks:read')
            || $this->apiKeyHasPermission('tasks:write')
            || $this->apiKeyHasPermission('tasks:operate');
    }

    protected function actorCanWriteTasks(Tenant $tenant): bool
    {
        $user = request()->user();

        if ($user instanceof User && $this->canWriteTasks($user, $tenant)) {
            return true;
        }

        return $this->apiKeyHasPermission('*')
            || $this->apiKeyHasPermission('tasks:write');
    }

    protected function actorCanOperateTasks(Tenant $tenant): bool
    {
        $user = request()->user();

        if ($user instanceof User && $this->canWriteTasks($user, $tenant)) {
            return true;
        }

        return $this->apiKeyHasPermission('*')
            || $this->apiKeyHasPermission('tasks:operate');
    }
}
