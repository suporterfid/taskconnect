<?php

namespace App\Application\Scheduling;

/**
 * Wall-clock budget for a scheduler tick (R5).
 * Stops new claims when elapsed time reaches the configured limit.
 */
final class TickBudget
{
    /**
     * @param  (callable(): float)|null  $now  Returns unix time with fractions (microtime); injectable for tests.
     */
    public function __construct(
        private readonly float $startedAt,
        private readonly float $limitSeconds,
        private readonly ?\Closure $now = null,
    ) {
    }

    public static function fromConfig(?\Closure $now = null): self
    {
        $target = max(1.0, (float) config('scheduler.target_duration_seconds', 45));
        $margin = max(0.0, (float) config('scheduler.budget_safety_margin_seconds', 5));
        $phpMax = (int) ini_get('max_execution_time');

        // Prefer the configured target; never claim into the PHP max_execution_time danger zone.
        $limit = $target;
        if ($phpMax > 0) {
            $limit = min($limit, max(1.0, $phpMax - $margin));
        }

        $clock = $now ?? static fn (): float => microtime(true);

        return new self($clock(), $limit, $now);
    }

    public function limitSeconds(): float
    {
        return $this->limitSeconds;
    }

    public function elapsedSeconds(): float
    {
        $now = ($this->now ?? static fn (): float => microtime(true))();

        return max(0.0, $now - $this->startedAt);
    }

    public function canClaimMore(): bool
    {
        return $this->elapsedSeconds() < $this->limitSeconds;
    }

    public function exhausted(): bool
    {
        return ! $this->canClaimMore();
    }
}
