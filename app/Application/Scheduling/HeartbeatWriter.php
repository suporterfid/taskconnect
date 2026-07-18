<?php

namespace App\Application\Scheduling;

use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\SystemHeartbeat;
use DateTimeImmutable;

final class HeartbeatWriter
{
    public function __construct(private readonly Clock $clock) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(string $name, array $meta = []): SystemHeartbeat
    {
        return SystemHeartbeat::record($name, $this->clock->nowUtc(), $meta);
    }
}
