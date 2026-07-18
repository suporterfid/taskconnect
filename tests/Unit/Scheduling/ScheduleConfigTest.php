<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\InvalidScheduleConfigException;
use App\Domain\Scheduling\ScheduleConfig;
use App\Domain\Scheduling\ScheduleKind;
use PHPUnit\Framework\TestCase;

class ScheduleConfigTest extends TestCase
{
    public function test_builds_daily_schedule_from_array(): void
    {
        $config = ScheduleConfig::fromArray([
            'kind' => 'daily_at',
            'timezone' => 'America/Sao_Paulo',
            'time' => '09:00',
        ]);

        $this->assertSame(ScheduleKind::DailyAt, $config->kind);
        $this->assertSame('09:00', $config->time);
    }

    public function test_requires_kind_and_timezone(): void
    {
        $this->expectException(InvalidScheduleConfigException::class);
        ScheduleConfig::fromArray(['kind' => 'daily_at']);
    }
}
