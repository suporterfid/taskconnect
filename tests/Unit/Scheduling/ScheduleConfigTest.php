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

    public function test_builds_cron_schedule_from_array(): void
    {
        $config = ScheduleConfig::fromArray([
            'kind' => 'cron',
            'timezone' => 'UTC',
            'cron_expression' => '*/15 * * * *',
        ]);

        $this->assertSame(ScheduleKind::Cron, $config->kind);
        $this->assertSame('*/15 * * * *', $config->cronExpression);
    }

    public function test_rejects_invalid_cron_expression(): void
    {
        $this->expectException(InvalidScheduleConfigException::class);
        ScheduleConfig::fromArray([
            'kind' => 'cron',
            'timezone' => 'UTC',
            'cron_expression' => 'not-a-cron',
        ]);
    }

    public function test_delayed_once_helper(): void
    {
        $config = ScheduleConfig::delayedOnce('2026-08-01T12:00:00Z', 'UTC');

        $this->assertSame(ScheduleKind::Once, $config->kind);
        $this->assertSame('2026-08-01T12:00:00+00:00', $config->at?->format('c'));
    }
}
