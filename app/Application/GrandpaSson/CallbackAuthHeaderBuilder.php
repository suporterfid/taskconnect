<?php

namespace App\Application\GrandpaSson;

use App\Domain\Auth\CallbackHmac;
use App\Domain\Shared\PublicId;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use RuntimeException;

/**
 * Builds R8 callback auth headers (bearer + HMAC envelope).
 */
final class CallbackAuthHeaderBuilder
{
    public function __construct(
        private readonly TokenClientInterface $tokenClient,
        private readonly CallbackHmac $hmac,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function build(TaskRun $run, TaskRunAttempt $attempt, Task $task, ?string $rawBody): array
    {
        if (! (bool) config('grandpasson.outbound_enabled', false)) {
            return [];
        }

        $hmacSecret = (string) config('grandpasson.callback_hmac_secret', '');
        if ($hmacSecret === '') {
            throw new RuntimeException('TC_CALLBACK_HMAC_SECRET is required when GrandpaSSOn outbound auth is enabled.');
        }

        $scope = (string) config('grandpasson.callback_scope', 'tasks:callback');
        $token = $this->tokenClient->clientCredentialsToken($scope);

        $timestamp = (string) time();
        $nonce = PublicId::generate('nce');
        $body = $rawBody ?? '';
        $signature = $this->hmac->sign($hmacSecret, $timestamp, $nonce, $body);

        return [
            'Authorization' => 'Bearer '.$token->accessToken,
            // X-TC-Task-Id / X-TC-Workspace are always sent by HttpDeliveryService::executionHeaders().
            'X-TC-Timestamp' => $timestamp,
            'X-TC-Nonce' => $nonce,
            'X-TC-Signature' => $signature,
            'X-TC-Run-Id' => $run->public_id,
            'X-TC-Attempt' => (string) $attempt->attempt_number,
        ];
    }
}
