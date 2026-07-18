<?php

namespace Tests\Unit\Execution;

use App\Domain\Execution\RetryDecider;
use App\Domain\Execution\RetryPolicy;
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
}
