<?php

namespace App\Domain\Execution;

use App\Domain\Shared\PublicId;
use DateTimeImmutable;

final class IdempotencyKeyGenerator
{
    public function forScheduledRun(int $taskId, DateTimeImmutable $scheduledFor): string
    {
        return hash('sha256', sprintf('sched:%d:%s', $taskId, $scheduledFor->format('Y-m-d\TH:i:s\Z')));
    }

    public function forManualRun(?string $clientKey = null): string
    {
        return $clientKey !== null && $clientKey !== ''
            ? 'manual:'.$clientKey
            : PublicId::generate('idem');
    }

    public function forTestRun(): string
    {
        return PublicId::generate('idem');
    }

    /** Fresh delivery Idempotency-Key group for DLQ replay (R6). */
    public function forDlqReplay(): string
    {
        return PublicId::generate('idem');
    }
}
