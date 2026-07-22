<?php

namespace Tests\Unit\Execution;

use App\Application\Execution\HttpDeliveryService;
use App\Application\Execution\RequestSnapshotRedactor;
use App\Application\GrandpaSson\CallbackAuthHeaderBuilder;
use App\Application\Secrets\SecretService;
use App\Domain\Auth\CallbackHmac;
use App\Domain\Execution\Outbound\OutboundPolicy;
use App\Domain\Execution\Outbound\OutboundPolicyConfig;
use App\Domain\Scheduling\TaskTypeCatalog;
use App\Infrastructure\HttpClient\PinnedHttpResponse;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ArrayDnsResolver;
use Tests\Support\FakeGrandpaSsonTokenClient;
use Tests\Support\MockPinnedHttpTransport;
use Tests\TestCase;

class CallbackAuthDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbound_delivery_includes_bearer_and_hmac_headers_when_enabled(): void
    {
        config([
            'grandpasson.outbound_enabled' => true,
            'grandpasson.callback_hmac_secret' => 'hmac-test-secret',
            'grandpasson.callback_scope' => 'tasks:callback',
        ]);

        $transport = new MockPinnedHttpTransport(
            new PinnedHttpResponse(
                statusCode: 200,
                headers: [],
                bodyTruncated: 'ok',
                bodySha256: hash('sha256', 'ok'),
                bodyTruncatedFlag: false,
                finalUrl: 'http://receiver:8080/hook',
                redirectCount: 0,
            ),
        );

        $policy = OutboundPolicy::fromConfig(
            OutboundPolicyConfig::fromArray([
                'allowed_ports' => [80, 443, 8080],
                'allow_http' => true,
                'testing_allow_hosts' => ['receiver'],
                'metadata_hosts' => [],
                'metadata_ips' => [],
            ]),
            new ArrayDnsResolver(['receiver' => ['127.0.0.1']]),
        );

        $service = new HttpDeliveryService(
            outboundPolicy: $policy,
            transport: $transport,
            redactor: new RequestSnapshotRedactor,
            secretService: app(SecretService::class),
            taskTypeCatalog: app(TaskTypeCatalog::class),
            callbackAuthHeaderBuilder: new CallbackAuthHeaderBuilder(
                new FakeGrandpaSsonTokenClient('gss-callback-token'),
                new CallbackHmac,
            ),
        );

        $task = Task::factory()->create([
            'url_or_path' => 'http://receiver:8080/hook',
            'body_template' => '{"ping":true}',
        ]);
        $task->load('environment');

        $run = TaskRun::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_id' => $task->id,
            'trigger_type' => 'scheduled',
            'occurrence_key' => 'occ-cb',
            'idempotency_key' => 'idem-cb',
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
        $this->assertSame('Bearer gss-callback-token', $headers['Authorization']);
        $this->assertSame($task->public_id, $headers['X-TC-Task-Id']);
        $this->assertSame($task->environment->public_id, $headers['X-TC-Workspace']);
        $this->assertArrayHasKey('X-TC-Timestamp', $headers);
        $this->assertArrayHasKey('X-TC-Nonce', $headers);
        $this->assertArrayHasKey('X-TC-Signature', $headers);

        $hmac = new CallbackHmac;
        $this->assertTrue($hmac->verify(
            secret: 'hmac-test-secret',
            timestamp: $headers['X-TC-Timestamp'],
            nonce: $headers['X-TC-Nonce'],
            rawBody: '{"ping":true}',
            signature: $headers['X-TC-Signature'],
            maxSkewSeconds: 600,
        ));
    }
}
