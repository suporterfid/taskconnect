<?php

namespace App\Domain\Execution;

use App\Domain\Shared\PublicId;
use DateTimeImmutable;

final class OccurrenceKeyGenerator
{
    public function forScheduled(DateTimeImmutable $scheduledFor): string
    {
        return $scheduledFor->format('Y-m-d\TH:i:s\Z');
    }

    public function forManual(string $idempotencyKey): string
    {
        return 'manual:'.$idempotencyKey;
    }

    public function forTest(?string $token = null): string
    {
        return 'test:'.($token ?? PublicId::generate());
    }

    public function forDlqReplay(string $idempotencyKey): string
    {
        return 'dlq:'.$idempotencyKey;
    }
}
