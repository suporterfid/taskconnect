<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\ScheduleConfig;
use App\Domain\Scheduling\ScheduleDescription;
use App\Domain\Scheduling\ScheduleKind;
use PHPUnit\Framework\TestCase;

class ScheduleDescriptionTest extends TestCase
{
    public function test_exposes_structured_parts_for_i18n(): void
    {
        $config = ScheduleConfig::fromArray([
            'kind' => 'weekly_on',
            'timezone' => 'America/New_York',
            'weekdays' => [1, 4],
            'time' => '14:30',
        ]);

        $description = ScheduleDescription::fromConfig($config);

        $this->assertSame(ScheduleKind::WeeklyOn, $description->kind);
        $this->assertSame([1, 4], $description->parts['weekdays']);
        $this->assertSame('14:30', $description->parts['time']);
        $this->assertSame('America/New_York', $description->parts['timezone']);
    }
}
