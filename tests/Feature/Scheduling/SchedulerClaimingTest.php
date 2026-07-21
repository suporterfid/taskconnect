<?php

namespace Tests\Feature\Scheduling;

use App\Application\Execution\HttpDeliveryService;
use App\Application\Scheduling\AttemptExecutor;
use App\Application\Scheduling\DueTaskClaimer;
use App\Application\Scheduling\StaleClaimRecovery;
use App\Application\Tasks\TaskLifecycleService;
use App\Domain\Execution\Enums\AttemptState;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Enums\TriggerType;
use App\Domain\Execution\OccurrenceKeyGenerator;
use App\Domain\Execution\Outbound\OutboundPolicy;
use App\Domain\Execution\Outbound\OutboundPolicyConfig;
use App\Domain\Shared\Clock;
use DateTimeImmutable;
use App\Infrastructure\HttpClient\PinnedHttpTransport;
use App\Infrastructure\HttpClient\PinnedHttpResponse;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ArrayDnsResolver;
use Tests\Support\CreatesScheduledTasks;
use Tests\Support\FixedClock;
use Tests\Support\MockPinnedHttpTransport;
use Tests\TestCase;

class SchedulerClaimingTest extends TestCase
{
    use CreatesScheduledTasks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Clock::class, FixedClock::at('2026-07-18T12:00:00Z'));

        $this->app->instance(OutboundPolicy::class, OutboundPolicy::fromConfig(
            OutboundPolicyConfig::fromArray([
                'allowed_ports' => [80, 443, 8080],
                'allow_http' => true,
                'platform_allow_hosts' => [],
                'testing_allow_hosts' => ['receiver'],
                'metadata_hosts' => ['metadata.google.internal'],
                'metadata_ips' => ['169.254.169.254'],
            ]),
            new ArrayDnsResolver(['receiver' => ['127.0.0.1']]),
        ));

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

    public function test_sequential_claimers_do_not_duplicate_scheduled_runs(): void
    {
        $task = $this->createActiveTaskDueAt('2026-07-18T11:45:00Z');
        $claimer = $this->app->make(DueTaskClaimer::class);

        $first = $claimer->claim(10);
        $second = $claimer->claim(10);

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
        $this->assertSame(1, TaskRun::query()->where('task_id', $task->id)->count());
    }

    public function test_existing_occurrence_key_prevents_duplicate_claim_run(): void
    {
        $dueAt = '2026-07-18T11:45:00Z';
        $task = $this->createActiveTaskDueAt($dueAt);
        $scheduledFor = new DateTimeImmutable($dueAt);
        $occurrenceKey = $this->app->make(OccurrenceKeyGenerator::class)->forScheduled($scheduledFor);

        TaskRun::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_id' => $task->id,
            'trigger_type' => TriggerType::Scheduled,
            'scheduled_for' => $scheduledFor,
            'occurrence_key' => $occurrenceKey,
            'idempotency_key' => 'preexisting-occurrence',
            'run_state' => RunState::Pending,
            'attempt_count' => 1,
        ]);

        $claimed = $this->app->make(DueTaskClaimer::class)->claim(10);

        $this->assertCount(0, $claimed);
        $this->assertSame(1, TaskRun::query()->where('task_id', $task->id)->count());
    }

    public function test_skip_backlog_advances_next_run_at_past_now(): void
    {
        $task = $this->createActiveTaskDueAt('2026-07-18T11:00:00Z');
        $claimer = $this->app->make(DueTaskClaimer::class);

        $claimer->claim(10);

        $task->refresh();
        $this->assertNotNull($task->next_run_at);
        $this->assertTrue($task->next_run_at->greaterThan('2026-07-18T12:00:00Z'));
    }

    public function test_retries_do_not_change_next_run_at(): void
    {
        $task = $this->createActiveTaskDueAt('2026-07-18T11:45:00Z');
        $claimer = $this->app->make(DueTaskClaimer::class);

        $this->app->forgetInstance(HttpDeliveryService::class);
        $this->app->forgetInstance(AttemptExecutor::class);
        $this->app->instance(PinnedHttpTransport::class, new MockPinnedHttpTransport(
            new PinnedHttpResponse(
                statusCode: 503,
                headers: [],
                bodyTruncated: 'fail',
                bodySha256: hash('sha256', 'fail'),
                bodyTruncatedFlag: false,
                finalUrl: 'http://receiver:8080/hook',
                redirectCount: 0,
            ),
        ));

        $claimed = $claimer->claim(10);
        $task->refresh();
        $nextRunAtAfterClaim = $task->next_run_at?->format('Y-m-d H:i:s');

        $executor = $this->app->make(AttemptExecutor::class);
        $executor->execute($claimed[0]->attempt);

        $task->refresh();
        $this->assertSame($nextRunAtAfterClaim, $task->next_run_at?->format('Y-m-d H:i:s'));
        $this->assertSame(RunState::RetryWait, TaskRun::query()->first()->run_state);
    }

    public function test_manual_run_does_not_change_next_run_at(): void
    {
        $task = $this->createActiveTaskDueAt('2026-07-18T12:30:00Z');
        $originalNextRunAt = $task->next_run_at?->format('Y-m-d H:i:s');

        $run = $this->app->make(TaskLifecycleService::class)->queueManualRun($task);

        $task->refresh();
        $this->assertSame($originalNextRunAt, $task->next_run_at?->format('Y-m-d H:i:s'));
        $this->assertSame(TriggerType::Manual, $run->trigger_type);
    }

    public function test_stale_claim_recovery_schedules_retry_preserving_idempotency_key(): void
    {
        $task = $this->createActiveTaskDueAt('2026-07-18T11:45:00Z');
        $claimer = $this->app->make(DueTaskClaimer::class);
        $claimed = $claimer->claim(10);
        $run = $claimed[0]->run;
        $idempotencyKey = $run->idempotency_key;

        $attempt = $claimed[0]->attempt;
        $attempt->attempt_state = AttemptState::Running;
        $attempt->claim_token = 'stale-token';
        $attempt->claim_expires_at = '2026-07-18T11:50:00Z';
        $attempt->save();

        $run->run_state = RunState::Running;
        $run->save();

        $recovered = $this->app->make(StaleClaimRecovery::class)->recover();

        $this->assertSame(1, $recovered);
        $run->refresh();
        $this->assertSame($idempotencyKey, $run->idempotency_key);
        $this->assertSame(RunState::RetryWait, $run->run_state);
        $this->assertSame(AttemptState::Interrupted, $attempt->fresh()->attempt_state);
    }
}
