<?php

namespace App\Domain\Execution;

use DateTimeImmutable;

final class RetryDecider
{
    public function isRetryableHttpStatus(int $statusCode, RetryPolicy $policy): bool
    {
        if ($statusCode >= 200 && $statusCode <= 299) {
            return false;
        }

        if ($policy->retryableStatusCodes !== null) {
            return in_array($statusCode, $policy->retryableStatusCodes, true);
        }

        if (in_array($statusCode, [408, 425, 429], true)) {
            return true;
        }

        if ($statusCode >= 500 && $statusCode <= 599) {
            return true;
        }

        if ($statusCode >= 400 && $statusCode <= 499) {
            return false;
        }

        return $statusCode === 0;
    }

    public function isTransportRetryable(?string $transportError): bool
    {
        return $transportError !== null && $transportError !== '';
    }

    public function shouldRetry(
        int $statusCode,
        ?string $transportError,
        int $attemptNumber,
        RetryPolicy $policy,
    ): bool {
        if ($attemptNumber >= $policy->maxAttempts) {
            return false;
        }

        if ($this->isTransportRetryable($transportError)) {
            return true;
        }

        return $this->isRetryableHttpStatus($statusCode, $policy);
    }

    public function nextDelaySeconds(
        int $attemptNumber,
        RetryPolicy $policy,
        ?int $retryAfterSeconds = null,
    ): int {
        if ($policy->honorRetryAfter && $retryAfterSeconds !== null && $retryAfterSeconds > 0) {
            return $retryAfterSeconds;
        }

        $index = max(0, $attemptNumber - 1);

        if ($index >= count($policy->delaySeconds)) {
            return $policy->delaySeconds[count($policy->delaySeconds) - 1];
        }

        return $policy->delaySeconds[$index];
    }

    /**
     * @param  array<string, list<string>>  $responseHeaders
     */
    public function parseRetryAfter(array $responseHeaders): ?int
    {
        $value = $responseHeaders['Retry-After'][0]
            ?? $responseHeaders['retry-after'][0]
            ?? null;

        if ($value === null) {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        $date = DateTimeImmutable::createFromFormat(DATE_RFC7231, $value);

        if ($date === false) {
            return null;
        }

        return max(0, $date->getTimestamp() - time());
    }

    public function isSuccess(int $statusCode): bool
    {
        return $statusCode >= 200 && $statusCode <= 299;
    }
}
