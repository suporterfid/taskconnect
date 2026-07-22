<?php

namespace Tests\Feature\Scheduling;

use App\Application\Execution\HttpDeliveryService;
use App\Domain\Execution\Outbound\OutboundPolicy;
use App\Domain\Execution\RetryPolicy;
use App\Infrastructure\HttpClient\GuzzlePinnedHttpTransport;
use App\Infrastructure\HttpClient\PinnedHttpTransport;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hits the docker `receiver` /_delay endpoint with real DNS-pinned transport
 * to prove task.timeout_ms bounds the socket wait (R4 / #62).
 */
class DeliveryTimeoutMsTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_timeout_ms_fails_against_slow_receiver_while_higher_succeeds(): void
    {
        if (! $this->receiverReachable()) {
            $this->markTestSkipped('docker receiver is not reachable from the app container');
        }

        config([
            'outbound.allow_http' => true,
            'outbound.allowed_ports' => [80, 443, 8080],
            'outbound.testing_allow_hosts' => ['receiver'],
            'outbound.connect_timeout' => 5,
            'outbound.total_timeout' => 15,
        ]);
        $this->app->forgetInstance(OutboundPolicy::class);
        $this->app->forgetInstance(GuzzlePinnedHttpTransport::class);
        $this->app->forgetInstance(PinnedHttpTransport::class);
        $this->app->forgetInstance(HttpDeliveryService::class);

        $service = $this->app->make(HttpDeliveryService::class);

        $slowUrl = 'http://receiver:8080/_delay?ms=2500';

        $shortTask = Task::factory()->create([
            'url_or_path' => $slowUrl,
            'timeout_ms' => 1000,
            'retry_policy_json' => (new RetryPolicy(maxAttempts: 1, delaySeconds: []))->toArray(),
        ]);
        $longTask = Task::factory()->create([
            'tenant_id' => $shortTask->tenant_id,
            'environment_id' => $shortTask->environment_id,
            'url_or_path' => $slowUrl,
            'timeout_ms' => 8000,
            'retry_policy_json' => (new RetryPolicy(maxAttempts: 1, delaySeconds: []))->toArray(),
        ]);

        $shortResult = $service->deliver($this->makeAttempt($shortTask, 'occ-short', 'idem-short'));
        $longResult = $service->deliver($this->makeAttempt($longTask, 'occ-long', 'idem-long'));

        $this->assertFalse($shortResult->blocked);
        $this->assertNotNull($shortResult->response);
        $this->assertSame(0, $shortResult->response->statusCode);
        $this->assertNotNull($shortResult->response->transportError);

        $this->assertFalse($longResult->blocked);
        $this->assertNotNull($longResult->response);
        $this->assertSame(200, $longResult->response->statusCode);
        $this->assertNull($longResult->response->transportError);
    }

    private function makeAttempt(Task $task, string $occurrenceKey, string $idempotencyKey): TaskRunAttempt
    {
        $run = TaskRun::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_id' => $task->id,
            'trigger_type' => 'manual',
            'occurrence_key' => $occurrenceKey,
            'idempotency_key' => $idempotencyKey,
            'run_state' => 'pending',
            'attempt_count' => 1,
        ]);

        return TaskRunAttempt::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_run_id' => $run->id,
            'attempt_number' => 1,
            'attempt_state' => 'pending',
        ]);
    }

    private function receiverReachable(): bool
    {
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen('receiver', 8080, $errno, $errstr, 1.0);
        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
