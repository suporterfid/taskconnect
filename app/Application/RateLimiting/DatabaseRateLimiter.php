<?php

namespace App\Application\RateLimiting;

use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\RateLimitBucket;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * MySQL/SQLite-backed fixed-window rate limits (R15). No Redis.
 */
final class DatabaseRateLimiter
{
    public function __construct(
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws TooManyRequestsHttpException
     */
    public function hitOrFail(string $bucketKey, int $maxAttempts, int $windowSeconds = 60): void
    {
        $maxAttempts = max(1, $maxAttempts);
        $windowSeconds = max(1, $windowSeconds);
        $now = $this->clock->nowUtc();

        $retryAfter = DB::transaction(function () use ($bucketKey, $maxAttempts, $windowSeconds, $now): ?int {
            /** @var RateLimitBucket|null $bucket */
            $bucket = RateLimitBucket::query()
                ->where('bucket_key', $bucketKey)
                ->lockForUpdate()
                ->first();

            if ($bucket === null) {
                RateLimitBucket::query()->create([
                    'bucket_key' => $bucketKey,
                    'hits' => 1,
                    'resets_at' => $this->format($now->modify(sprintf('+%d seconds', $windowSeconds))),
                ]);

                return null;
            }

            $resetsAt = DateTimeImmutable::createFromInterface($bucket->resets_at);
            if ($resetsAt <= $now) {
                $bucket->hits = 1;
                $bucket->resets_at = $this->format($now->modify(sprintf('+%d seconds', $windowSeconds)));
                $bucket->save();

                return null;
            }

            if ($bucket->hits >= $maxAttempts) {
                return max(1, $resetsAt->getTimestamp() - $now->getTimestamp());
            }

            $bucket->hits++;
            $bucket->save();

            return null;
        });

        if ($retryAfter !== null) {
            throw new TooManyRequestsHttpException(
                $retryAfter,
                'Submission rate limit exceeded. Try again later.',
            );
        }
    }

    public function limitForWorkspace(?Tenant $tenant, ?Environment $environment): int
    {
        if ($environment !== null && $environment->submit_rate_limit_per_minute !== null) {
            return max(1, (int) $environment->submit_rate_limit_per_minute);
        }

        return max(1, (int) config('scheduler.submit_rate_limit_per_minute', 60));
    }

    public function bucketKeyForWorkspace(Tenant $tenant, Environment $environment): string
    {
        return sprintf('submit:%d:%d', $tenant->id, $environment->id);
    }

    private function format(DateTimeImmutable $at): string
    {
        return $at->format('Y-m-d H:i:s');
    }
}
