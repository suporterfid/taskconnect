<?php

namespace Tests\Unit\Execution;

use App\Application\Execution\EgressHostRateLimiter;
use App\Application\Execution\HttpDeliveryService;
use App\Application\Execution\RequestSnapshotRedactor;
use App\Application\Execution\RobotsTxtChecker;
use App\Application\RateLimiting\DatabaseRateLimiter;
use App\Application\Secrets\SecretService;
use App\Domain\Execution\Outbound\OutboundPolicy;
use App\Domain\Execution\Outbound\OutboundPolicyConfig;
use App\Domain\Execution\Outbound\RobotsTxtParser;
use App\Domain\Scheduling\TaskTypeCatalog;
use App\Infrastructure\HttpClient\PinnedHttpResponse;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ArrayDnsResolver;
use Tests\Support\MockPinnedHttpTransport;
use Tests\TestCase;

class HttpDeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_sends_idempotency_headers_and_persists_redacted_snapshot(): void
    {
        [$service, $transport] = $this->makeService();

        $task = Task::factory()->create([
            'url_or_path' => 'http://receiver:8080/hook',
            'headers_json' => ['Authorization' => 'Bearer secret-token'],
        ]);

        $run = TaskRun::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_id' => $task->id,
            'trigger_type' => 'scheduled',
            'occurrence_key' => '2026-07-18T12:00:00Z',
            'idempotency_key' => 'idem-test-key',
            'run_state' => 'pending',
            'attempt_count' => 1,
        ]);

        $attempt = TaskRunAttempt::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_run_id' => $run->id,
            'attempt_number' => 1,
            'attempt_state' => 'pending',
        ]);

        $result = $service->deliver($attempt);

        $this->assertFalse($result->blocked);
        $this->assertCount(1, $transport->requests);
        $headers = $transport->requests[0]->headers;
        $this->assertSame('idem-test-key', $headers['Idempotency-Key']);
        $this->assertSame('idem-test-key', $headers['X-Task-Idempotency-Key']);
        $this->assertSame($run->public_id, $headers['X-Task-Run-Id']);
        $this->assertSame('1', $headers['X-Task-Attempt']);

        $attempt->refresh();
        $this->assertSame('[REDACTED]', $attempt->request_headers_redacted_json['Authorization']);
    }

    public function test_delivery_always_includes_tc_task_id_and_workspace_headers_when_grandpasson_outbound_disabled(): void
    {
        config(['grandpasson.outbound_enabled' => false]);

        [$service, $transport] = $this->makeService();

        $task = Task::factory()->create([
            'url_or_path' => 'http://receiver:8080/hook',
        ]);
        $task->load('environment');

        $run = TaskRun::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_id' => $task->id,
            'trigger_type' => 'scheduled',
            'occurrence_key' => '2026-07-23T12:00:00Z',
            'idempotency_key' => 'idem-tc-headers',
            'run_state' => 'pending',
            'attempt_count' => 1,
        ]);

        $attempt = TaskRunAttempt::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_run_id' => $run->id,
            'attempt_number' => 1,
            'attempt_state' => 'pending',
        ]);

        $result = $service->deliver($attempt);

        $this->assertFalse($result->blocked);
        $headers = $transport->requests[0]->headers;
        $this->assertSame('idem-tc-headers', $headers['Idempotency-Key']);
        $this->assertSame($task->public_id, $headers['X-TC-Task-Id']);
        $this->assertSame($task->environment->public_id, $headers['X-TC-Workspace']);
        $this->assertArrayNotHasKey('X-TC-Signature', $headers);
    }

    public function test_retry_attempts_reuse_the_same_idempotency_key(): void
    {
        [$service, $transport] = $this->makeService();

        $task = Task::factory()->create([
            'url_or_path' => 'http://receiver:8080/hook',
        ]);

        $run = TaskRun::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_id' => $task->id,
            'trigger_type' => 'scheduled',
            'occurrence_key' => '2026-07-18T13:00:00Z',
            'idempotency_key' => 'stable-across-retries',
            'run_state' => 'retry_wait',
            'attempt_count' => 2,
        ]);

        $first = TaskRunAttempt::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_run_id' => $run->id,
            'attempt_number' => 1,
            'attempt_state' => 'pending',
        ]);

        $second = TaskRunAttempt::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_run_id' => $run->id,
            'attempt_number' => 2,
            'attempt_state' => 'pending',
        ]);

        $service->deliver($first);
        $service->deliver($second);

        $this->assertCount(2, $transport->requests);
        $this->assertSame(
            $transport->requests[0]->headers['Idempotency-Key'],
            $transport->requests[1]->headers['Idempotency-Key'],
        );
        $this->assertSame('stable-across-retries', $transport->requests[0]->headers['Idempotency-Key']);
        $this->assertSame('1', $transport->requests[0]->headers['X-Task-Attempt']);
        $this->assertSame('2', $transport->requests[1]->headers['X-Task-Attempt']);

        // Receiver double: one effect per Idempotency-Key despite two deliveries.
        $effects = [];
        foreach ($transport->requests as $request) {
            $key = $request->headers['Idempotency-Key'];
            $effects[$key] = ($effects[$key] ?? 0) + 1;
        }
        $this->assertSame(1, count($effects));
        $appliedOnce = [];
        foreach ($transport->requests as $request) {
            $key = $request->headers['Idempotency-Key'];
            if (isset($appliedOnce[$key])) {
                continue;
            }
            $appliedOnce[$key] = true;
        }
        $this->assertCount(1, $appliedOnce);
    }

    public function test_internal_profile_blocks_non_allowlisted_host_before_connect(): void
    {
        [$service] = $this->makeService();

        $task = Task::factory()->create([
            'url_or_path' => 'https://public.example/hook',
            'egress_profile' => 'internal',
        ]);

        $run = TaskRun::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_id' => $task->id,
            'trigger_type' => 'scheduled',
            'occurrence_key' => '2026-07-18T14:00:00Z',
            'idempotency_key' => 'idem-blocked',
            'run_state' => 'pending',
            'attempt_count' => 1,
        ]);

        $attempt = TaskRunAttempt::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_run_id' => $run->id,
            'attempt_number' => 1,
            'attempt_state' => 'pending',
        ]);

        $result = $service->deliver($attempt);

        $this->assertTrue($result->blocked);
        $this->assertSame('host_not_allowlisted', $result->blockReason);
    }

    public function test_timeout_ms_is_applied_to_pinned_request(): void
    {
        [$service, $transport] = $this->makeService();

        $task = Task::factory()->create([
            'url_or_path' => 'http://receiver:8080/hook',
            'timeout_ms' => 2500,
        ]);

        $run = TaskRun::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_id' => $task->id,
            'trigger_type' => 'scheduled',
            'occurrence_key' => '2026-07-18T15:00:00Z',
            'idempotency_key' => 'idem-timeout-ms',
            'run_state' => 'pending',
            'attempt_count' => 1,
        ]);

        $attempt = TaskRunAttempt::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_run_id' => $run->id,
            'attempt_number' => 1,
            'attempt_state' => 'pending',
        ]);

        $service->deliver($attempt);

        $this->assertCount(1, $transport->requests);
        $this->assertSame(3, $transport->requests[0]->totalTimeout);
        $this->assertSame(3, $transport->requests[0]->connectTimeout);
    }

    public function test_timeout_ms_is_capped_by_global_outbound_ceiling(): void
    {
        [$service, $transport] = $this->makeService([
            'connect_timeout' => 5,
            'total_timeout' => 4,
        ]);

        $task = Task::factory()->create([
            'url_or_path' => 'http://receiver:8080/hook',
            'timeout_ms' => 60_000,
        ]);

        $run = TaskRun::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_id' => $task->id,
            'trigger_type' => 'scheduled',
            'occurrence_key' => '2026-07-18T16:00:00Z',
            'idempotency_key' => 'idem-timeout-cap',
            'run_state' => 'pending',
            'attempt_count' => 1,
        ]);

        $attempt = TaskRunAttempt::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_run_id' => $run->id,
            'attempt_number' => 1,
            'attempt_state' => 'pending',
        ]);

        $service->deliver($attempt);

        $this->assertCount(1, $transport->requests);
        $this->assertSame(4, $transport->requests[0]->totalTimeout);
        $this->assertSame(4, $transport->requests[0]->connectTimeout);
    }

    /**
     * @param  array<string, mixed>  $outboundOverrides
     * @return array{0: HttpDeliveryService, 1: MockPinnedHttpTransport}
     */
    private function makeService(array $outboundOverrides = []): array
    {
        $transport = new MockPinnedHttpTransport(
            new PinnedHttpResponse(
                statusCode: 200,
                headers: ['Content-Type' => ['application/json']],
                bodyTruncated: '{"ok":true}',
                bodySha256: hash('sha256', '{"ok":true}'),
                bodyTruncatedFlag: false,
                finalUrl: 'http://receiver:8080/hook',
                redirectCount: 0,
            ),
        );

        $policy = OutboundPolicy::fromConfig(
            OutboundPolicyConfig::fromArray(array_merge([
                'allowed_ports' => [80, 443, 8080],
                'allow_http' => true,
                'platform_allow_hosts' => [],
                'testing_allow_hosts' => ['receiver'],
                'metadata_hosts' => ['metadata.google.internal'],
                'metadata_ips' => ['169.254.169.254'],
                'connect_timeout' => 5,
                'total_timeout' => 15,
            ], $outboundOverrides)),
            new ArrayDnsResolver([
                'receiver' => ['127.0.0.1'],
                'public.example' => ['93.184.216.34'],
            ]),
        );

        $service = new HttpDeliveryService(
            outboundPolicy: $policy,
            transport: $transport,
            redactor: new RequestSnapshotRedactor,
            secretService: app(SecretService::class),
            taskTypeCatalog: app(TaskTypeCatalog::class),
            callbackAuthHeaderBuilder: app(\App\Application\GrandpaSson\CallbackAuthHeaderBuilder::class),
            egressHostRateLimiter: new EgressHostRateLimiter(app(DatabaseRateLimiter::class)),
            robotsTxtChecker: new RobotsTxtChecker($policy, $transport, new RobotsTxtParser),
        );

        return [$service, $transport];
    }

    public function test_public_crawl_host_rate_limit_returns_retryable_429(): void
    {
        config([
            'outbound.host_rate_limit_per_minute' => ['public-crawl' => 1, 'api' => 60],
            'outbound.host_rate_limit_window_seconds' => 60,
            'outbound.robots_txt.enabled' => false,
        ]);

        [$service, $transport] = $this->makeService([
            'profiles' => [
                'public-crawl' => [],
            ],
        ]);

        $task = Task::factory()->create([
            'url_or_path' => 'http://public.example/page',
            'egress_profile' => 'public-crawl',
        ]);

        $first = $this->makeAttempt($task, 'occ-rl-1', 'idem-rl-1');
        $second = $this->makeAttempt($task, 'occ-rl-2', 'idem-rl-2');

        $ok = $service->deliver($first);
        $limited = $service->deliver($second);

        $this->assertFalse($ok->blocked);
        $this->assertCount(1, $transport->requests);

        $this->assertFalse($limited->blocked);
        $this->assertSame(429, $limited->response?->statusCode);
        $this->assertSame('host_rate_limited', $limited->response?->transportError);
        $this->assertSame(1, count($transport->requests));
    }

    public function test_public_crawl_robots_disallow_blocks_delivery(): void
    {
        config([
            'outbound.host_rate_limit_per_minute' => ['public-crawl' => 0, 'api' => 0],
            'outbound.robots_txt.enabled' => true,
            'outbound.robots_txt.cache_seconds' => 60,
            'outbound.user_agent' => 'OpenHttpScheduler/1.1',
        ]);

        $robotsBody = "User-agent: *\nDisallow: /private\n";
        $transport = new MockPinnedHttpTransport(
            new PinnedHttpResponse(
                statusCode: 200,
                headers: ['Content-Type' => ['text/plain']],
                bodyTruncated: $robotsBody,
                bodySha256: hash('sha256', $robotsBody),
                bodyTruncatedFlag: false,
                finalUrl: 'http://public.example/robots.txt',
                redirectCount: 0,
            ),
            new PinnedHttpResponse(
                statusCode: 200,
                headers: [],
                bodyTruncated: 'should-not-run',
                bodySha256: hash('sha256', 'should-not-run'),
                bodyTruncatedFlag: false,
                finalUrl: 'http://public.example/private',
                redirectCount: 0,
            ),
        );

        $policy = OutboundPolicy::fromConfig(
            OutboundPolicyConfig::fromArray([
                'allowed_ports' => [80, 443, 8080],
                'allow_http' => true,
                'testing_allow_hosts' => [],
                'profiles' => ['public-crawl' => []],
            ]),
            new ArrayDnsResolver([
                'public.example' => ['93.184.216.34'],
            ]),
        );

        $service = new HttpDeliveryService(
            outboundPolicy: $policy,
            transport: $transport,
            redactor: new RequestSnapshotRedactor,
            secretService: app(SecretService::class),
            taskTypeCatalog: app(TaskTypeCatalog::class),
            callbackAuthHeaderBuilder: app(\App\Application\GrandpaSson\CallbackAuthHeaderBuilder::class),
            egressHostRateLimiter: new EgressHostRateLimiter(app(DatabaseRateLimiter::class)),
            robotsTxtChecker: new RobotsTxtChecker($policy, $transport, new RobotsTxtParser),
        );

        $task = Task::factory()->create([
            'url_or_path' => 'http://public.example/private/page',
            'egress_profile' => 'public-crawl',
        ]);

        $result = $service->deliver($this->makeAttempt($task, 'occ-robots', 'idem-robots'));

        $this->assertTrue($result->blocked);
        $this->assertSame('robots_disallow', $result->blockReason);
        // Only the robots.txt fetch should have been sent.
        $this->assertCount(1, $transport->requests);
        $this->assertSame('/robots.txt', parse_url($transport->requests[0]->endpoint->url, PHP_URL_PATH));
    }

    private function makeAttempt(Task $task, string $occurrenceKey, string $idempotencyKey): TaskRunAttempt
    {
        $run = TaskRun::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_id' => $task->id,
            'trigger_type' => 'scheduled',
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
}
