<?php

namespace App\Application\Notifications;

use App\Domain\Execution\Outbound\EgressProfile;
use App\Domain\Execution\Outbound\OutboundPolicy;
use App\Domain\Execution\Outbound\OutboundPolicyViolation;
use App\Infrastructure\HttpClient\PinnedHttpRequest;
use App\Infrastructure\HttpClient\PinnedHttpTransport;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use Throwable;

/**
 * Posts a JSON DLQ alert to a workspace webhook URL via DNS-pinned transport (R13).
 */
final class DeadRunWebhookSender
{
    public function __construct(
        private readonly OutboundPolicy $outboundPolicy,
        private readonly PinnedHttpTransport $transport,
    ) {
    }

    /**
     * @return array{ok: bool, status_code: ?int, host: ?string, reason: ?string}
     */
    public function send(TaskRun $run, Environment $environment): array
    {
        $url = trim((string) $environment->dead_run_webhook_url);
        if ($url === '') {
            return ['ok' => false, 'status_code' => null, 'host' => null, 'reason' => 'missing_url'];
        }

        $host = parse_url($url, PHP_URL_HOST);
        $host = is_string($host) ? $host : null;

        try {
            $validated = $this->outboundPolicy->validateUrl($url, [], EgressProfile::PublicCrawl);
            $payload = $this->payload($run, $environment);
            $body = json_encode($payload, JSON_THROW_ON_ERROR);

            $response = $this->transport->send(new PinnedHttpRequest(
                method: 'POST',
                endpoint: $validated,
                headers: [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'TaskConnect-DLQ-Alert/1.0',
                ],
                body: $body,
                verifyTls: true,
                followRedirects: false,
                connectTimeout: (int) config('scheduler.failure_webhook_connect_timeout', 2),
                totalTimeout: (int) config('scheduler.failure_webhook_total_timeout', 5),
                responseBodyLimit: 1024,
                egressProfile: EgressProfile::PublicCrawl,
            ));

            $ok = $response->statusCode >= 200 && $response->statusCode < 300;

            return [
                'ok' => $ok,
                'status_code' => $response->statusCode,
                'host' => $host,
                'reason' => $ok ? null : 'non_2xx',
            ];
        } catch (OutboundPolicyViolation $exception) {
            return [
                'ok' => false,
                'status_code' => null,
                'host' => $host,
                'reason' => $exception->getMessage() !== '' ? 'policy:'.$exception->getMessage() : 'policy_violation',
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'status_code' => null,
                'host' => $host,
                'reason' => 'transport_error',
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TaskRun $run, Environment $environment): array
    {
        $run->loadMissing(['task', 'environment.tenant']);

        return [
            'event' => 'task_run.dead',
            'workspace_id' => $environment->public_id,
            'tenant_id' => $environment->tenant?->public_id,
            'run_id' => $run->public_id,
            'task_id' => $run->task?->public_id,
            'task_type' => $run->task?->task_type,
            'final_http_status' => $run->final_http_status,
            'final_error_code' => $run->final_error_code,
            'finished_at' => $run->finished_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
