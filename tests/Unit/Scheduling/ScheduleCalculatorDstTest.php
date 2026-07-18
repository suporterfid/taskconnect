<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\ScheduleCalculator;
use App\Domain\Scheduling\ScheduleConfig;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Support\FixedClock;

class ScheduleCalculatorDstTest extends TestCase
{
    private ScheduleCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new ScheduleCalculator(FixedClock::at('2024-01-01T00:00:00Z'));
    }

    public function test_new_york_spring_forward_uses_next_valid_local_time(): void
    {
        $config = ScheduleConfig::fromArray([
            'kind' => 'daily_at',
            'timezone' => 'America/New_York',
            'time' => '02:30',
        ]);

        $next = $this->calculator->nextRunAt(
            $config,
            new DateTimeImmutable('2024-03-10T04:00:00Z', new DateTimeZone('UTC')),
        );

        $this->assertSame('2024-03-10T07:00:00+00:00', $next?->format('c'));
    }

    public function test_new_york_fall_back_uses_earlier_utc_instant(): void
    {
        $config = ScheduleConfig::fromArray([
            'kind' => 'daily_at',
            'timezone' => 'America/New_York',
            'time' => '01:30',
        ]);

        $next = $this->calculator->nextRunAt(
            $config,
            new DateTimeImmutable('2024-11-03T04:00:00Z', new DateTimeZone('UTC')),
        );

        $this->assertSame('2024-11-03T05:30:00+00:00', $next?->format('c'));
    }

    public function test_sao_paulo_historical_spring_forward_gap(): void
    {
        $config = ScheduleConfig::fromArray([
            'kind' => 'daily_at',
            'timezone' => 'America/Sao_Paulo',
            'time' => '00:30',
        ]);

        $next = $this->calculator->nextRunAt(
            $config,
            new DateTimeImmutable('2018-11-03T04:00:00Z', new DateTimeZone('UTC')),
        );

        $this->assertSame('2018-11-04T03:00:00+00:00', $next?->format('c'));
    }

    public function test_sao_paulo_historical_fall_back_uses_earlier_utc_instant(): void
    {
        $config = ScheduleConfig::fromArray([
            'kind' => 'daily_at',
            'timezone' => 'America/Sao_Paulo',
            'time' => '00:30',
        ]);

        $next = $this->calculator->nextRunAt(
            $config,
            new DateTimeImmutable('2019-02-16T03:00:00Z', new DateTimeZone('UTC')),
        );

        $this->assertSame('2019-02-17T03:30:00+00:00', $next?->format('c'));
    }
}
