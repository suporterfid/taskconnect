<?php

namespace Tests\Feature\Scheduling;

use App\Application\Scheduling\AttemptExecutor;
use App\Application\Scheduling\PendingRunClaimer;
use App\Application\Tasks\TaskLifecycleService;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Outbound\OutboundPolicy;
use App\Domain\Execution\Outbound\OutboundPolicyConfig;
use App\Domain\Execution\RetryPolicy;
use App\Domain\Shared\Clock;
use App\Infrastructure\HttpClient\PinnedHttpResponse;
use App\Infrastructure\HttpClient\PinnedHttpTransport;
use App\Infrastructure\Persistence\Eloquent\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ArrayDnsResolver;
use Tests\Support\CreatesScheduledTasks;
use Tests\Support\FixedClock;
use Tests\Support\MockPinnedHttpTransport;
use Tests\TestCase;

class SchedulerAuditTest extends TestCase
{
    use CreatesScheduledTasks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Clock::class, FixedClock::at('2026-07-18T12:00:00Z'));
        config([
            'scheduler.failure_emails_enabled' => false,
            'scheduler.failure_webhooks_enabled' => false,
        ]);

        $this->app->instance(OutboundPolicy::class, OutboundPolicy::fromConfig(
            OutboundPolicyConfig::fromArray([
                'allowed_ports' => [80, 443, 8080],
                'allow_http' => true,
                'testing_allow_hosts' => ['receiver'],
            ]),
            new ArrayDnsResolver(['receiver' => ['127.0.0.1']]),
        ));

        $this->app->forgetInstance(PinnedHttpTransport::class);
        $this->app->forgetInstance(\App\Application\Execution\HttpDeliveryService::class);
        $this->app->forgetInstance(AttemptExecutor::class);
        $this->app->instance(PinnedHttpTransport::class, new MockPinnedHttpTransport(
            new PinnedHttpResponse(
                statusCode: 200,
                headers: [],
                bodyTruncated: 'ok',
                bodySha256: hash('sha256', 'ok'),
                bodyTruncatedFlag: false,
                finalUrl: 'http://receiver:8080/hook',
                redirectCount: 0,
            ),
        ));
    }

    public function test_claim_and_successful_delivery_are_audited(): void
    {
        $task = $this->createActiveTaskDueAt('2026-07-18T12:30:00Z');
        $run = $this->app->make(TaskLifecycleService::class)->queueManualRun($task);

        $claimed = $this->app->make(PendingRunClaimer::class)->claim(10);
        $this->assertCount(1, $claimed);

        $claimLog = AuditLog::query()->where('action', 'scheduler.claim')->first();
        $this->assertNotNull($claimLog);
        $this->assertSame($run->public_id, $claimLog->resource_id);
        $this->assertSame($run->environment_id, $claimLog->environment_id);
        $this->assertSame('pending', $claimLog->summary_json['source'] ?? null);

        $this->app->make(AttemptExecutor::class)->execute($claimed[0]->attempt);

        $deliveryLog = AuditLog::query()->where('action', 'scheduler.delivery')->first();
        $this->assertNotNull($deliveryLog);
        $this->assertSame('succeeded', $deliveryLog->summary_json['outcome'] ?? null);
        $this->assertSame(200, $deliveryLog->summary_json['http_status'] ?? null);
        $this->assertSame($run->environment_id, $deliveryLog->environment_id);
    }

    public function test_dead_delivery_outcome_is_audited(): void
    {
        $this->app->forgetInstance(PinnedHttpTransport::class);
        $this->app->forgetInstance(\App\Application\Execution\HttpDeliveryService::class);
        $this->app->forgetInstance(AttemptExecutor::class);
        $this->app->instance(PinnedHttpTransport::class, new MockPinnedHttpTransport(
            new PinnedHttpResponse(
                statusCode: 400,
                headers: [],
                bodyTruncated: 'bad',
                bodySha256: hash('sha256', 'bad'),
                bodyTruncatedFlag: false,
                finalUrl: 'http://receiver:8080/hook',
                redirectCount: 0,
            ),
        ));

        $task = $this->createActiveTaskDueAt('2026-07-18T12:30:00Z');
        $task->retry_policy_json = (new RetryPolicy(maxAttempts: 1, delaySeconds: []))->toArray();
        $task->save();

        $run = $this->app->make(TaskLifecycleService::class)->queueManualRun($task);
        $claimed = $this->app->make(PendingRunClaimer::class)->claim(10);
        $this->app->make(AttemptExecutor::class)->execute($claimed[0]->attempt);

        $run->refresh();
        $this->assertSame(RunState::Dead, $run->run_state);

        $deliveryLog = AuditLog::query()->where('action', 'scheduler.delivery')->first();
        $this->assertNotNull($deliveryLog);
        $this->assertSame('dead', $deliveryLog->summary_json['outcome'] ?? null);
        $this->assertSame(400, $deliveryLog->summary_json['http_status'] ?? null);
    }
}
