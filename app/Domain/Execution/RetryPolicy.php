<?php

namespace App\Domain\Execution;

use InvalidArgumentException;

final readonly class RetryPolicy
{
    /** @var list<int> */
    public const DEFAULT_DELAY_SECONDS = [60, 300, 900, 3600, 21600];

    /**
     * @param  list<int>  $delaySeconds
     * @param  list<int>|null  $retryableStatusCodes
     * @param  list<array{0: int, 1: int}>|null  $successStatusRanges
     */
    public function __construct(
        public int $maxAttempts = 6,
        public array $delaySeconds = self::DEFAULT_DELAY_SECONDS,
        public bool $honorRetryAfter = true,
        public ?int $maxRetryWindowSeconds = null,
        public ?array $retryableStatusCodes = null,
        public ?array $successStatusRanges = null,
    ) {
    }

    public static function default(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        if ($data === null || $data === []) {
            return self::default();
        }

        $delays = $data['delay_seconds'] ?? $data['delays'] ?? self::DEFAULT_DELAY_SECONDS;

        if (! is_array($delays)) {
            $delays = self::DEFAULT_DELAY_SECONDS;
        }

        return new self(
            maxAttempts: (int) ($data['max_attempts'] ?? 6),
            delaySeconds: array_map('intval', $delays),
            honorRetryAfter: (bool) ($data['honor_retry_after'] ?? true),
            maxRetryWindowSeconds: isset($data['max_retry_window_seconds'])
                ? (int) $data['max_retry_window_seconds']
                : null,
            retryableStatusCodes: isset($data['retryable_status_codes'])
                ? array_map('intval', (array) $data['retryable_status_codes'])
                : null,
            successStatusRanges: self::normalizeSuccessStatusRanges(
                $data['success_status_ranges'] ?? null,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'max_attempts' => $this->maxAttempts,
            'delay_seconds' => $this->delaySeconds,
            'honor_retry_after' => $this->honorRetryAfter,
            'max_retry_window_seconds' => $this->maxRetryWindowSeconds,
            'retryable_status_codes' => $this->retryableStatusCodes,
            'success_status_ranges' => $this->successStatusRanges,
        ];
    }

    /**
     * @return list<array{0: int, 1: int}>|null
     */
    private static function normalizeSuccessStatusRanges(mixed $ranges): ?array
    {
        if ($ranges === null) {
            return null;
        }

        if (! is_array($ranges)) {
            throw new InvalidArgumentException('success_status_ranges must be an array of [min, max] pairs.');
        }

        if ($ranges === []) {
            return [];
        }

        $normalized = [];

        foreach ($ranges as $range) {
            if (! is_array($range) || count($range) < 2) {
                throw new InvalidArgumentException('Each success_status_ranges entry must be a [min, max] pair.');
            }

            $min = (int) $range[0];
            $max = (int) $range[1];

            if ($min > $max) {
                throw new InvalidArgumentException('success_status_ranges min must be <= max.');
            }

            if ($min < 100 || $max > 599) {
                throw new InvalidArgumentException('success_status_ranges values must be between 100 and 599.');
            }

            $normalized[] = [$min, $max];
        }

        return $normalized;
    }
}
