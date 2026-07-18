<?php

namespace Tests\Unit\Execution;

use App\Application\Execution\HttpDeliveryService;
use App\Application\Execution\RequestSnapshotRedactor;
use App\Application\Secrets\SecretService;
use App\Domain\Execution\Outbound\OutboundPolicy;
use App\Domain\Execution\Outbound\OutboundPolicyConfig;
use App\Infrastructure\HttpClient\PinnedHttpTransport;
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
            OutboundPolicyConfig::fromArray([
                'allowed_ports' => [80, 443, 8080],
                'allow_http' => true,
                'platform_allow_hosts' => [],
                'testing_allow_hosts' => ['receiver'],
                'metadata_hosts' => ['metadata.google.internal'],
                'metadata_ips' => ['169.254.169.254'],
            ]),
            new ArrayDnsResolver(['receiver' => ['127.0.0.1']]),
        );

        $service = new HttpDeliveryService(
            outboundPolicy: $policy,
            transport: $transport,
            redactor: new RequestSnapshotRedactor,
            secretService: app(SecretService::class),
        );

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
        $this->assertSame('idem-test-key', $transport->requests[0]->headers['X-Task-Idempotency-Key']);

        $attempt->refresh();
        $this->assertSame('[REDACTED]', $attempt->request_headers_redacted_json['Authorization']);
    }
}
