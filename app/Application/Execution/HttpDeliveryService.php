<?php

namespace App\Application\Execution;

use App\Application\Secrets\SecretService;
use App\Domain\EndpointProfiles\AuthMode;
use App\Domain\Execution\Outbound\OutboundPolicy;
use App\Domain\Execution\Outbound\OutboundPolicyViolation;
use App\Domain\Execution\Outbound\ValidatedEndpoint;
use App\Infrastructure\HttpClient\PinnedHttpTransport;
use App\Infrastructure\HttpClient\PinnedHttpRequest;
use App\Infrastructure\HttpClient\PinnedHttpResponse;
use App\Infrastructure\Persistence\Eloquent\EndpointProfile;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use Illuminate\Support\Arr;

final class HttpDeliveryService
{
    public function __construct(
        private readonly OutboundPolicy $outboundPolicy,
        private readonly PinnedHttpTransport $transport,
        private readonly RequestSnapshotRedactor $redactor,
        private readonly SecretService $secretService,
    ) {
    }

    public function deliver(TaskRunAttempt $attempt): DeliveryResult
    {
        $run = $attempt->run()->firstOrFail();
        $task = $run->task()->with(['endpointProfile.secret', 'tenant'])->firstOrFail();

        try {
            $resolved = $this->resolveRequest($task);
            $this->outboundPolicy->validateHeaders($resolved['headers']);

            $additionalAllowHosts = $this->tenantAllowHosts($task);
            $validated = $this->outboundPolicy->validateUrl($resolved['url'], $additionalAllowHosts);
            $endpoint = $this->selectPinnedEndpoint($validated);
            $profile = $task->endpointProfile;

            $headers = array_merge(
                $this->outboundPolicy->sanitizeHeaders($resolved['headers']),
                $this->executionHeaders($run, $attempt),
            );

            $this->persistRequestSnapshot($attempt, $resolved['url'], $headers, $resolved['body']);

            $response = $this->transport->send(new PinnedHttpRequest(
                method: $task->method,
                endpoint: $endpoint,
                headers: $headers,
                body: $resolved['body'],
                verifyTls: $profile?->verify_tls ?? true,
                followRedirects: $profile?->follow_redirects ?? false,
                connectTimeout: $profile?->connect_timeout,
                totalTimeout: $profile?->total_timeout,
                additionalAllowHosts: $additionalAllowHosts,
            ));

            return new DeliveryResult(
                response: $response,
                blocked: false,
                blockReason: null,
            );
        } catch (OutboundPolicyViolation $exception) {
            return new DeliveryResult(
                response: null,
                blocked: true,
                blockReason: $exception->reasonCode,
                blockMessage: $exception->getMessage(),
            );
        }
    }

    /**
     * @return array{url: string, headers: array<string, string>, body: ?string}
     */
    private function resolveRequest(Task $task): array
    {
        $headers = Arr::map($task->headers_json ?? [], static fn ($value) => (string) $value);
        $url = $task->url_or_path;
        $profile = $task->endpointProfile;

        if ($profile !== null) {
            $base = rtrim($profile->base_url, '/');
            $path = $url;
            $url = $base.(str_starts_with($path, '/') ? $path : '/'.$path);
            $headers = array_merge($profile->visibleHeaders(), $headers);
            $headers = $this->applyProfileAuth($profile, $url, $headers);
        }

        if (! empty($task->query_json)) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator.http_build_query($task->query_json);
        }

        $body = $task->body_template;

        if ($body !== null && $task->content_type !== null) {
            $headers['Content-Type'] = $task->content_type;
        }

        return [
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function applyProfileAuth(EndpointProfile $profile, string &$url, array $headers): array
    {
        if ($profile->auth_mode === AuthMode::None || $profile->secret_id === null || $profile->secret === null) {
            return $headers;
        }

        $secretValue = $this->secretService->decrypt($profile->secret);

        return match ($profile->auth_mode) {
            AuthMode::Bearer => array_merge($headers, ['Authorization' => 'Bearer '.$secretValue]),
            AuthMode::Basic => array_merge($headers, ['Authorization' => 'Basic '.base64_encode($secretValue)]),
            AuthMode::StaticHeader => array_merge($headers, [
                ($profile->authConfig()['header_name'] ?? 'Authorization') => $secretValue,
            ]),
            AuthMode::QueryToken => $headers,
            AuthMode::None => $headers,
        };
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function persistRequestSnapshot(
        TaskRunAttempt $attempt,
        string $url,
        array $headers,
        ?string $body,
    ): void {
        $attempt->request_url_redacted = $this->redactor->redactUrl($url);
        $attempt->request_headers_redacted_json = $this->redactor->redactHeaders($headers);
        $attempt->request_body_redacted = $this->redactor->redactBody($body);
        $attempt->save();
    }

    /**
     * @return array<string, string>
     */
    private function executionHeaders(TaskRun $run, TaskRunAttempt $attempt): array
    {
        $idempotencyKey = (string) $run->idempotency_key;

        return [
            // v1 Extension R3 — stable per run; constant across retries of this run.
            'Idempotency-Key' => $idempotencyKey,
            // Compatibility alias (deprecated; remove after consumers migrate).
            'X-Task-Idempotency-Key' => $idempotencyKey,
            'X-Task-Run-Id' => $run->public_id,
            'X-Task-Attempt' => (string) $attempt->attempt_number,
        ];
    }

    /**
     * @return list<string>
     */
    private function tenantAllowHosts(Task $task): array
    {
        $tenant = $task->relationLoaded('tenant')
            ? $task->tenant
            : $task->tenant()->first();

        if ($tenant === null) {
            return [];
        }

        return array_values(array_filter(
            (array) ($tenant->outbound_allow_hosts ?? []),
            static fn ($host): bool => is_string($host) && $host !== '',
        ));
    }

    private function selectPinnedEndpoint(ValidatedEndpoint $validated): ValidatedEndpoint
    {
        return new ValidatedEndpoint(
            url: $validated->url,
            scheme: $validated->scheme,
            host: $validated->host,
            port: $validated->port,
            pinnedIp: $validated->resolvedIps[0],
            resolvedIps: $validated->resolvedIps,
            hostAllowlisted: $validated->hostAllowlisted,
        );
    }
}

final readonly class DeliveryResult
{
    public function __construct(
        public ?PinnedHttpResponse $response,
        public bool $blocked,
        public ?string $blockReason,
        public ?string $blockMessage = null,
    ) {
    }
}
