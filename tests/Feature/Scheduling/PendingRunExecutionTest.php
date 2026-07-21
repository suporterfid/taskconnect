<?php

namespace Tests\Feature\Scheduling;

use App\Application\Execution\HttpDeliveryService;
use App\Application\Scheduling\AttemptExecutor;
use App\Application\Scheduling\PendingRunClaimer;
use App\Application\Tasks\RunLifecycleService;
use App\Application\Tasks\TaskLifecycleService;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Enums\TriggerType;
use App\Domain\Execution\Outbound\OutboundPolicy;
use App\Domain\Execution\Outbound\OutboundPolicyConfig;
use App\Domain\Execution\RetryPolicy;
use App\Domain\Shared\Clock;
use App\Infrastructure\HttpClient\PinnedHttpResponse;
use App\Infrastructure\HttpClient\PinnedHttpTransport;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Mail\TaskRunFailedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ArrayDnsResolver;
use Tests\Support\CreatesScheduledTasks;
use Tests\Support\FixedClock;
use Tests\Support\MockPinnedHttpTransport;
use Tests\TestCase;

class PendingRunExecutionTest extends TestCase
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

        $this->bindSuccessfulTransport();
    }

    public function test_manual_run_is_claimed_and_executed_to_succeeded(): void
    {
        $task = $this->createActiveTaskDueAt('2026-07-18T12:30:00Z');
        $run = $this->app->make(TaskLifecycleService::class)->queueManualRun($task);

        $this->assertSame(RunState::Pending, $run->run_state);
        $this->assertSame(TriggerType::Manual, $run->trigger_type);

        $claimed = $this->app->make(PendingRunClaimer::class)->claim(10);
        $this->assertCount(1, $claimed);
        $this->assertSame($run->id, $claimed[0]->run->id);
        $this->assertNotNull($claimed[0]->attempt->claim_token);

        $this->app->make(AttemptExecutor::class)->execute($claimed[0]->attempt);

        $run->refresh();
        $this->assertSame(RunState::Succeeded, $run->run_state);
        $this->assertSame(200, $run->final_http_status);
    }

    public function test_test_run_is_claimed_and_executed(): void
    {
        $task = $this->createActiveTaskDueAt('2026-07-18T12:30:00Z');
        $run = $this->app->make(TaskLifecycleService::class)->queueTestRun($task);

        $this->assertSame(TriggerType::Test, $run->trigger_type);

        $claimed = $this->app->make(PendingRunClaimer::class)->claim(10);
        $this->assertCount(1, $claimed);

        $this->app->make(AttemptExecutor::class)->execute($claimed[0]->attempt);

        $run->refresh();
        $this->assertSame(RunState::Succeeded, $run->run_state);
    }

    public function test_manual_retry_from_dead_run_is_claimed_and_executed(): void
    {
        $task = $this->createActiveTaskDueAt('2026-07-18T12:30:00Z');
        $run = $this->app->make(TaskLifecycleService::class)->queueManualRun($task);

        $run->run_state = RunState::Dead;
        $run->finished_at = '2026-07-18T11:55:00Z';
        $run->save();

        $attempt = $this->app->make(RunLifecycleService::class)->manualRetry($run);
        $run->refresh();

        $this->assertSame(RunState::Pending, $run->run_state);
        $this->assertSame(2, $attempt->attempt_number);

        $claimed = $this->app->make(PendingRunClaimer::class)->claim(10);
        $this->assertCount(1, $claimed);
        $this->assertSame($attempt->id, $claimed[0]->attempt->id);

        $this->app->make(AttemptExecutor::class)->execute($claimed[0]->attempt);

        $run->refresh();
        $this->assertSame(RunState::Succeeded, $run->run_state);
    }

    public function test_failure_notifier_sends_mail_when_run_becomes_dead(): void
    {
        Mail::fake();

        $task = $this->createActiveTaskDueAt('2026-07-18T12:30:00Z');
        $task->retry_policy_json = (new RetryPolicy(maxAttempts: 1, delaySeconds: []))->toArray();
        $task->save();

        $this->bindFailingTransport();

        $run = $this->app->make(TaskLifecycleService::class)->queueManualRun($task);
        $claimed = $this->app->make(PendingRunClaimer::class)->claim(10);
        $this->assertCount(1, $claimed);

        $this->app->make(AttemptExecutor::class)->execute($claimed[0]->attempt);

        $run->refresh();
        $this->assertSame(RunState::Dead, $run->run_state);

        Mail::assertSent(TaskRunFailedMail::class, function (TaskRunFailedMail $mail) use ($run): bool {
            return $mail->run->is($run);
        });
    }

    public function test_pending_claimer_does_not_double_claim(): void
    {
        $task = $this->createActiveTaskDueAt('2026-07-18T12:30:00Z');
        $this->app->make(TaskLifecycleService::class)->queueManualRun($task);

        $first = $this->app->make(PendingRunClaimer::class)->claim(10);
        $second = $this->app->make(PendingRunClaimer::class)->claim(10);

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
        $this->assertSame(1, TaskRun::query()->count());
    }

    private function bindSuccessfulTransport(): void
    {
        $this->app->forgetInstance(HttpDeliveryService::class);
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

    private function bindFailingTransport(): void
    {
        $this->app->forgetInstance(HttpDeliveryService::class);
        $this->app->forgetInstance(AttemptExecutor::class);
        $this->app->instance(PinnedHttpTransport::class, new MockPinnedHttpTransport(
            new PinnedHttpResponse(
                statusCode: 500,
                headers: [],
                bodyTruncated: 'fail',
                bodySha256: hash('sha256', 'fail'),
                bodyTruncatedFlag: false,
                finalUrl: 'http://receiver:8080/hook',
                redirectCount: 0,
            ),
        ));
    }
}
