<?php

namespace App\Providers;

use App\Infrastructure\Persistence\Eloquent\ApiKey;
use App\Infrastructure\Persistence\Eloquent\EndpointProfile;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\PipelineInstance;
use App\Infrastructure\Persistence\Eloquent\Secret;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Policies\ApiKeyPolicy;
use App\Policies\EndpointProfilePolicy;
use App\Policies\EnvironmentPolicy;
use App\Policies\MemberPolicy;
use App\Policies\PipelineInstancePolicy;
use App\Policies\SecretPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TaskRunPolicy;
use App\Policies\TenantPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected $policies = [
        Tenant::class => TenantPolicy::class,
        Environment::class => EnvironmentPolicy::class,
        Secret::class => SecretPolicy::class,
        ApiKey::class => ApiKeyPolicy::class,
        EndpointProfile::class => EndpointProfilePolicy::class,
        Task::class => TaskPolicy::class,
        TaskRun::class => TaskRunPolicy::class,
        PipelineInstance::class => PipelineInstancePolicy::class,
        TenantMembership::class => MemberPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
