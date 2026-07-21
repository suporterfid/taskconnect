<?php

namespace Tests\Unit\Execution;

use App\Domain\Execution\RetryDecider;
use App\Domain\Execution\RetryPolicy;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class RetryDeciderTest extends TestCase
{
    private RetryDecider $decider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->decider = new RetryDecider;
    }

    public function test_default_delay_schedule(): void
    {
        $policy = RetryPolicy::default();

        $this->assertSame(60, $this->decider->nextDelaySeconds(1, $policy));
        $this->assertSame(300, $this->decider->nextDelaySeconds(2, $policy));
        $this->assertSame(900, $this->decider->nextDelaySeconds(3, $policy));
        $this->assertSame(3600, $this->decider->nextDelaySeconds(4, $policy));
        $this->assertSame(21600, $this->decider->nextDelaySeconds(5, $policy));
    }

    public function test_honors_retry_after_header(): void
    {
        $policy = RetryPolicy::default();

        $this->assertSame(120, $this->decider->nextDelaySeconds(2, $policy, 120));
    }

    public function test_does_not_retry_most_4xx(): void
    {
        $policy = RetryPolicy::default();

        $this->assertFalse($this->decider->shouldRetry(404, null, 1, $policy));
        $this->assertTrue($this->decider->shouldRetry(429, null, 1, $policy));
        $this->assertTrue($this->decider->shouldRetry(503, null, 1, $policy));
    }

    public function test_stops_after_max_attempts(): void
    {
        $policy = RetryPolicy::default();

        $this->assertFalse($this->decider->shouldRetry(503, null, 6, $policy));
    }

    public function test_stops_when_max_retry_window_elapsed(): void
    {
        $policy = new RetryPolicy(
            maxAttempts: 6,
            delaySeconds: RetryPolicy::DEFAULT_DELAY_SECONDS,
            maxRetryWindowSeconds: 3600,
        );
        $runStartedAt = new DateTimeImmutable('2026-07-18T12:00:00Z');
        $now = new DateTimeImmutable('2026-07-18T13:00:00Z');

        $this->assertFalse($this->decider->shouldRetry(503, null, 2, $policy, $runStartedAt, $now));
    }

    public function test_retries_within_max_retry_window(): void
    {
        $policy = new RetryPolicy(
            maxAttempts: 6,
            delaySeconds: RetryPolicy::DEFAULT_DELAY_SECONDS,
            maxRetryWindowSeconds: 3600,
        );
        $runStartedAt = new DateTimeImmutable('2026-07-18T12:00:00Z');
        $now = new DateTimeImmutable('2026-07-18T12:30:00Z');

        $this->assertTrue($this->decider->shouldRetry(503, null, 2, $policy, $runStartedAt, $now));
    }

    public function test_max_retry_window_ignored_without_run_started_at(): void
    {
        $policy = new RetryPolicy(
            maxAttempts: 6,
            delaySeconds: RetryPolicy::DEFAULT_DELAY_SECONDS,
            maxRetryWindowSeconds: 60,
        );

        $this->assertTrue($this->decider->shouldRetry(503, null, 2, $policy, null, new DateTimeImmutable('now')));
    }

    public function test_default_success_is_2xx(): void
    {
        $this->assertTrue($this->decider->isSuccess(200));
        $this->assertTrue($this->decider->isSuccess(204, RetryPolicy::default()));
        $this->assertFalse($this->decider->isSuccess(404));
    }

    public function test_custom_success_status_ranges(): void
    {
        $policy = new RetryPolicy(
            maxAttempts: 3,
            delaySeconds: [60],
            successStatusRanges: [[204, 204]],
        );

        $this->assertTrue($this->decider->isSuccess(204, $policy));
        $this->assertFalse($this->decider->isSuccess(200, $policy));
        $this->assertFalse($this->decider->shouldRetry(200, null, 1, $policy));
        $this->assertTrue($this->decider->shouldRetry(503, null, 1, $policy));
    }

    public function test_success_status_ranges_round_trip(): void
    {
        $policy = RetryPolicy::fromArray([
            'max_attempts' => 2,
            'success_status_ranges' => [[200, 201], [204, 204]],
        ]);

        $this->assertSame([[200, 201], [204, 204]], $policy->successStatusRanges);
        $this->assertSame([[200, 201], [204, 204]], $policy->toArray()['success_status_ranges']);
        $this->assertTrue($this->decider->isSuccess(201, $policy));
        $this->assertFalse($this->decider->isSuccess(202, $policy));
    }

    public function test_invalid_success_status_ranges_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RetryPolicy::fromArray([
            'success_status_ranges' => [[300, 200]],
        ]);
    }
}
