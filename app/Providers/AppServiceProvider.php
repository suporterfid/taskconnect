<?php

namespace App\Providers;

use App\Application\Execution\HttpDeliveryService;
use App\Application\Execution\RequestSnapshotRedactor;
use App\Application\Scheduling\AttemptExecutor;
use App\Application\Scheduling\DueTaskClaimer;
use App\Application\Scheduling\HeartbeatWriter;
use App\Application\Scheduling\RetryClaimer;
use App\Application\Scheduling\SchedulerCycleRunner;
use App\Application\Scheduling\StaleClaimRecovery;
use App\Application\Tasks\RunLifecycleService;
use App\Application\Tasks\TaskLifecycleService;
use App\Application\Tasks\TaskScheduleService;
use App\Domain\Execution\AttemptStateMachine;
use App\Domain\Execution\IdempotencyKeyGenerator;
use App\Domain\Execution\OccurrenceKeyGenerator;
use App\Domain\Execution\Outbound\DnsResolverInterface;
use App\Domain\Execution\Outbound\OutboundPolicy;
use App\Domain\Execution\Outbound\OutboundPolicyConfig;
use App\Domain\Execution\RetryDecider;
use App\Domain\Execution\RunStateMachine;
use App\Domain\Scheduling\ScheduleCalculator;
use App\Domain\Shared\Clock;
use App\Domain\Shared\SystemClock;
use App\Infrastructure\Dns\SystemDnsResolver;
use App\Infrastructure\HttpClient\GuzzlePinnedHttpTransport;
use App\Infrastructure\HttpClient\PinnedHttpTransport;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(DnsResolverInterface::class, SystemDnsResolver::class);

        $this->app->singleton(OutboundPolicy::class, function ($app): OutboundPolicy {
            return OutboundPolicy::fromConfig(
                OutboundPolicyConfig::fromArray(config('outbound')),
                $app->make(DnsResolverInterface::class),
            );
        });

        $this->app->singleton(GuzzlePinnedHttpTransport::class);
        $this->app->singleton(PinnedHttpTransport::class, GuzzlePinnedHttpTransport::class);
        $this->app->singleton(RequestSnapshotRedactor::class);
        $this->app->singleton(HttpDeliveryService::class);
        $this->app->singleton(ScheduleCalculator::class);
        $this->app->singleton(RunStateMachine::class);
        $this->app->singleton(AttemptStateMachine::class);
        $this->app->singleton(RetryDecider::class);
        $this->app->singleton(IdempotencyKeyGenerator::class);
        $this->app->singleton(OccurrenceKeyGenerator::class);
        $this->app->singleton(DueTaskClaimer::class);
        $this->app->singleton(RetryClaimer::class);
        $this->app->singleton(StaleClaimRecovery::class);
        $this->app->singleton(AttemptExecutor::class);
        $this->app->singleton(HeartbeatWriter::class);
        $this->app->singleton(SchedulerCycleRunner::class);
        $this->app->singleton(TaskScheduleService::class);
        $this->app->singleton(TaskLifecycleService::class);
        $this->app->singleton(RunLifecycleService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $this->app->make(\App\Application\Auth\BootstrapFirstAdmin::class)->ensureExists();
    }
}
