<?php

namespace App\Application\Execution;

use App\Application\RateLimiting\DatabaseRateLimiter;
use App\Domain\Execution\Outbound\EgressProfile;

/**
 * Per-destination-host rate limits for public-crawl / api (R7 §6.5).
 * Uses MySQL/SQLite rate_limit_buckets — no Redis.
 */
final class EgressHostRateLimiter
{
    public function __construct(
        private readonly DatabaseRateLimiter $limiter,
    ) {
    }

    /**
     * @return int|null Seconds until window reset when denied; null when allowed or not applicable.
     */
    public function check(EgressProfile $profile, string $host, int $tenantId): ?int
    {
        if ($profile !== EgressProfile::PublicCrawl && $profile !== EgressProfile::Api) {
            return null;
        }

        $max = $this->maxPerWindow($profile);
        if ($max <= 0) {
            return null;
        }

        $window = max(1, (int) config('outbound.host_rate_limit_window_seconds', 60));
        $key = sprintf('egress:%s:%d:%s', $profile->value, $tenantId, strtolower($host));

        return $this->limiter->tryHit($key, $max, $window);
    }

    private function maxPerWindow(EgressProfile $profile): int
    {
        $limits = config('outbound.host_rate_limit_per_minute', []);

        return max(0, (int) ($limits[$profile->value] ?? 0));
    }
}
