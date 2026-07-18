<?php

namespace App\Http\Controllers\Concerns;

use App\Auth\ApiKeyActor;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Http\Request;

trait ResolvesTenantContext
{
    protected function resolvedTenant(Request $request): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return $tenant;
    }

    protected function resolvedEnvironment(Request $request): Environment
    {
        /** @var Environment $environment */
        $environment = $request->attributes->get('environment');

        return $environment;
    }

    protected function resolvedActorUser(Request $request): User
    {
        $actor = $request->user();

        if ($actor instanceof User) {
            return $actor;
        }

        if ($actor instanceof ApiKeyActor) {
            return User::query()->findOrFail($actor->apiKey->created_by);
        }

        abort(401);
    }
}
