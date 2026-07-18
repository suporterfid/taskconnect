<?php

namespace Tests\Support;

use App\Domain\Shared\Clock;
use DateTimeImmutable;
use DateTimeZone;

final class FixedClock implements Clock
{
    public function __construct(
        private readonly DateTimeImmutable $fixed,
    ) {
    }

    public static function at(string $utcTimestamp): self
    {
        return new self(new DateTimeImmutable($utcTimestamp, new DateTimeZone('UTC')));
    }

    public function nowUtc(): DateTimeImmutable
    {
        return $this->fixed;
    }
}
