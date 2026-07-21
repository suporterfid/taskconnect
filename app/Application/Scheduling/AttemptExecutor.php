<?php

namespace App\Application\Scheduling;

use App\Application\Execution\DeliveryResult;
use App\Application\Execution\HttpDeliveryService;
use App\Application\Notifications\FailureNotifier;
use App\Domain\Execution\AttemptStateMachine;
use App\Domain\Execution\Enums\AttemptState;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Enums\TriggerType;
use App\Domain\Execution\RetryDecider;
use App\Domain\Execution\RetryPolicy;
use App\Domain\Execution\RunStateMachine;
use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use DateTimeImmutable;

final class AttemptExecutor
{
    public function __construct(
        private readonly Clock $clock,
        private readonly HttpDeliveryService $deliveryService,
        private readonly RetryDecider $retryDecider,
        private readonly RunStateMachine $runStateMachine,
        private readonly AttemptStateMachine $attemptStateMachine,
        private readonly FailureNotifier $failureNotifier,
    ) {
    }

    public function execute(TaskRunAttempt $attempt, ?RetryPolicy $overridePolicy = null): void
    {
        $run = $attempt->run()->firstOrFail();
        $task = $run->task()->firstOrFail();
        $now = $this->clock->nowUtc();

        $this->markRunning($run, $attempt, $now);
        $run->refresh();

        $policy = $overridePolicy ?? ($run->trigger_type === TriggerType::Test
            ? new RetryPolicy(maxAttempts: 1, delaySeconds: [])
            : $task->retryPolicy());

        $started = microtime(true);
        $result = $this->deliveryService->deliver($attempt);
        $durationMs = (int) round((microtime(true) - $started) * 1000);

        if ($result->blocked) {
            $this->finalizeBlocked($run, $attempt, $now, $durationMs, $result);

            return;
        }

        $response = $result->response;
        $statusCode = $response?->statusCode ?? 0;
        $transportError = $response?->transportError;

        $this->persistResponse($attempt, $response, $durationMs, $now);

        if ($this->retryDecider->isSuccess($statusCode, $policy)) {
            $this->finalizeSuccess($run, $attempt, $task, $now, $statusCode);

            return;
        }

        $retryAfter = $response !== null
            ? $this->retryDecider->parseRetryAfter($response->headers)
            : null;

        $runStartedAt = $run->started_at !== null
            ? DateTimeImmutable::createFromInterface($run->started_at)
            : null;

        if ($this->retryDecider->shouldRetry(
            $statusCode,
            $transportError,
            $attempt->attempt_number,
            $policy,
            $runStartedAt,
            $now,
        )) {
            $this->finalizeRetryable($run, $attempt, $task, $now, $statusCode, $transportError, $policy, $retryAfter);

            return;
        }

        $this->finalizeTerminal($run, $attempt, $task, $now, $statusCode, $transportError);
    }

    private function markRunning(TaskRun $run, TaskRunAttempt $attempt, DateTimeImmutable $now): void
    {
        $this->runStateMachine->assertCanTransition($run->run_state, RunState::Running);
        $this->attemptStateMachine->assertCanTransition($attempt->attempt_state, AttemptState::Running);

        $run->run_state = RunState::Running;
        $run->started_at ??= $now;
        $run->save();

        $attempt->attempt_state = AttemptState::Running;
        $attempt->started_at = $now;
        $attempt->claim_token = null;
        $attempt->claimed_at = null;
        $attempt->claim_expires_at = null;
        $attempt->save();
    }

    private function persistResponse(
        TaskRunAttempt $attempt,
        ?\App\Infrastructure\HttpClient\PinnedHttpResponse $response,
        int $durationMs,
        DateTimeImmutable $now,
    ): void {
        $attempt->duration_ms = $durationMs;
        $attempt->finished_at = $now;

        if ($response === null) {
            return;
        }

        $attempt->response_status = $response->statusCode ?: null;
        $attempt->response_headers_json = $response->headers;
        $attempt->response_body_truncated = $response->bodyTruncated;
        $attempt->response_body_sha256 = $response->bodySha256;

        if ($response->transportError !== null) {
            $attempt->transport_error_code = 'transport_error';
            $attempt->transport_error_message = $response->transportError;
        }

        $attempt->save();
    }

    private function finalizeSuccess(
        TaskRun $run,
        TaskRunAttempt $attempt,
        Task $task,
        DateTimeImmutable $now,
        int $statusCode,
    ): void {
        $this->attemptStateMachine->transition($attempt->attempt_state, AttemptState::Succeeded);
        $attempt->attempt_state = AttemptState::Succeeded;
        $attempt->save();

        $this->runStateMachine->transition($run->run_state, RunState::Succeeded);
        $run->run_state = RunState::Succeeded;
        $run->finished_at = $now;
        $run->final_http_status = $statusCode;
        $run->next_attempt_at = null;
        $run->save();

        $this->updateTaskLastRun($task, $now, RunState::Succeeded);
    }

    private function finalizeRetryable(
        TaskRun $run,
        TaskRunAttempt $attempt,
        Task $task,
        DateTimeImmutable $now,
        int $statusCode,
        ?string $transportError,
        RetryPolicy $policy,
        ?int $retryAfter,
    ): void {
        $attemptState = $transportError !== null && $statusCode === 0
            ? AttemptState::TimedOut
            : AttemptState::FailedRetryable;

        $this->attemptStateMachine->transition($attempt->attempt_state, $attemptState);
        $attempt->attempt_state = $attemptState;

        $delaySeconds = $this->retryDecider->nextDelaySeconds($attempt->attempt_number, $policy, $retryAfter);
        $nextRetryAt = $now->modify(sprintf('+%d seconds', $delaySeconds));
        $attempt->next_retry_at = $nextRetryAt;
        $attempt->save();

        $this->runStateMachine->transition($run->run_state, RunState::RetryWait);
        $run->run_state = RunState::RetryWait;
        $run->next_attempt_at = $nextRetryAt;
        $run->final_http_status = $statusCode ?: null;
        $run->final_error_code = $transportError !== null ? 'transport_error' : (string) $statusCode;
        $run->save();

        $this->updateTaskLastRun($task, $now, RunState::RetryWait);
    }

    private function finalizeTerminal(
        TaskRun $run,
        TaskRunAttempt $attempt,
        Task $task,
        DateTimeImmutable $now,
        int $statusCode,
        ?string $transportError,
    ): void {
        $attemptState = $transportError !== null && $statusCode === 0
            ? AttemptState::TimedOut
            : AttemptState::FailedTerminal;

        $this->attemptStateMachine->transition($attempt->attempt_state, $attemptState);
        $attempt->attempt_state = $attemptState;
        $attempt->save();

        $this->runStateMachine->transition($run->run_state, RunState::Dead);
        $run->run_state = RunState::Dead;
        $run->finished_at = $now;
        $run->final_http_status = $statusCode ?: null;
        $run->final_error_code = $transportError !== null ? 'transport_error' : (string) $statusCode;
        $run->next_attempt_at = null;
        $run->save();

        $this->updateTaskLastRun($task, $now, RunState::Dead);
        $this->failureNotifier->notifyDeadRun($run);
    }

    private function finalizeBlocked(
        TaskRun $run,
        TaskRunAttempt $attempt,
        DateTimeImmutable $now,
        int $durationMs,
        DeliveryResult $result,
    ): void {
        $attempt->duration_ms = $durationMs;
        $attempt->finished_at = $now;
        $this->attemptStateMachine->transition($attempt->attempt_state, AttemptState::Blocked);
        $attempt->attempt_state = AttemptState::Blocked;
        $attempt->transport_error_code = $result->blockReason;
        $attempt->transport_error_message = $result->blockMessage;
        $attempt->save();

        $this->runStateMachine->transition($run->run_state, RunState::Blocked);
        $run->run_state = RunState::Blocked;
        $run->finished_at = $now;
        $run->final_error_code = $result->blockReason;
        $run->next_attempt_at = null;
        $run->save();

        $task = $run->task()->firstOrFail();
        $this->updateTaskLastRun($task, $now, RunState::Blocked);
    }

    private function updateTaskLastRun(Task $task, DateTimeImmutable $now, RunState $state): void
    {
        $task->last_run_at = $now;
        $task->last_run_state = $state->value;
        $task->save();
    }
}
