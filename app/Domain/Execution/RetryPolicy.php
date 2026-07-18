<?php

namespace App\Domain\Execution;

final readonly class RetryPolicy
{
    /** @var list<int> */
    public const DEFAULT_DELAY_SECONDS = [60, 300, 900, 3600, 21600];

    /**
     * @param  list<int>  $delaySeconds
     * @param  list<int>|null  $retryableStatusCodes
     */
    public function __construct(
        public int $maxAttempts = 6,
        public array $delaySeconds = self::DEFAULT_DELAY_SECONDS,
        public bool $honorRetryAfter = true,
        public ?int $maxRetryWindowSeconds = null,
        public ?array $retryableStatusCodes = null,
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
        ];
    }
}
