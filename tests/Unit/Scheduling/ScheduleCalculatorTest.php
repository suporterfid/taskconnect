<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\ScheduleCalculator;
use App\Domain\Scheduling\ScheduleConfig;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Support\FixedClock;

class ScheduleCalculatorTest extends TestCase
{
    private ScheduleCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new ScheduleCalculator(FixedClock::at('2026-07-18T10:00:00Z'));
    }

    public function test_once_returns_future_instant_or_null_when_past(): void
    {
        $future = ScheduleConfig::fromArray([
            'kind' => 'once',
            'timezone' => 'UTC',
            'at' => '2026-07-19T12:00:00Z',
        ]);

        $past = ScheduleConfig::fromArray([
            'kind' => 'once',
            'timezone' => 'UTC',
            'at' => '2026-07-17T12:00:00Z',
        ]);

        $this->assertSame(
            '2026-07-19T12:00:00+00:00',
            $this->calculator->nextRunAt($future)?->format('c'),
        );
        $this->assertNull($this->calculator->nextRunAt($past));
    }

    public function test_every_n_minutes_uses_skip_backlog(): void
    {
        $config = ScheduleConfig::fromArray([
            'kind' => 'every_n_minutes',
            'timezone' => 'UTC',
            'interval_minutes' => 15,
            'starts_at' => '2026-07-18T09:00:00Z',
        ]);

        $next = $this->calculator->nextRunAt(
            $config,
            new DateTimeImmutable('2026-07-18T10:07:00Z', new DateTimeZone('UTC')),
        );

        $this->assertSame('2026-07-18T10:15:00+00:00', $next?->format('c'));
    }

    public function test_hourly_at_finds_next_minute_slot(): void
    {
        $config = ScheduleConfig::fromArray([
            'kind' => 'hourly_at',
            'timezone' => 'UTC',
            'minute' => 20,
        ]);

        $next = $this->calculator->nextRunAt(
            $config,
            new DateTimeImmutable('2026-07-18T10:25:00Z', new DateTimeZone('UTC')),
        );

        $this->assertSame('2026-07-18T11:20:00+00:00', $next?->format('c'));
    }

    public function test_daily_at_returns_next_local_time(): void
    {
        $config = ScheduleConfig::fromArray([
            'kind' => 'daily_at',
            'timezone' => 'America/Sao_Paulo',
            'time' => '09:00',
        ]);

        $next = $this->calculator->nextRunAt(
            $config,
            new DateTimeImmutable('2026-07-18T14:00:00Z', new DateTimeZone('UTC')),
        );

        $this->assertSame('2026-07-19T12:00:00+00:00', $next?->format('c'));
    }

    public function test_weekly_on_selects_next_matching_weekday(): void
    {
        $config = ScheduleConfig::fromArray([
            'kind' => 'weekly_on',
            'timezone' => 'UTC',
            'weekdays' => [1, 4],
            'time' => '14:30',
        ]);

        $next = $this->calculator->nextRunAt(
            $config,
            new DateTimeImmutable('2026-07-18T10:00:00Z', new DateTimeZone('UTC')),
        );

        $this->assertSame('2026-07-20T14:30:00+00:00', $next?->format('c'));
    }

    public function test_monthly_on_day_skips_missing_days(): void
    {
        $config = ScheduleConfig::fromArray([
            'kind' => 'monthly_on_day',
            'timezone' => 'UTC',
            'day' => 31,
            'time' => '08:00',
        ]);

        $next = $this->calculator->nextRunAt(
            $config,
            new DateTimeImmutable('2026-01-31T09:00:00Z', new DateTimeZone('UTC')),
        );

        $this->assertSame('2026-03-31T08:00:00+00:00', $next?->format('c'));
    }

    public function test_business_days_at_skips_weekends(): void
    {
        $config = ScheduleConfig::fromArray([
            'kind' => 'business_days_at',
            'timezone' => 'UTC',
            'time' => '09:00',
        ]);

        $next = $this->calculator->nextRunAt(
            $config,
            new DateTimeImmutable('2026-07-17T10:00:00Z', new DateTimeZone('UTC')),
        );

        $this->assertSame('2026-07-20T09:00:00+00:00', $next?->format('c'));
    }

    public function test_cron_finds_next_expression_match_in_timezone(): void
    {
        $config = ScheduleConfig::fromArray([
            'kind' => 'cron',
            'timezone' => 'UTC',
            'cron_expression' => '30 14 * * *',
        ]);

        $next = $this->calculator->nextRunAt(
            $config,
            new DateTimeImmutable('2026-07-18T10:00:00Z', new DateTimeZone('UTC')),
        );

        $this->assertSame('2026-07-18T14:30:00+00:00', $next?->format('c'));
    }

    public function test_delayed_once_helper_builds_once_schedule(): void
    {
        $config = ScheduleConfig::delayedOnce('2026-07-20T15:00:00Z');

        $this->assertSame(
            '2026-07-20T15:00:00+00:00',
            $this->calculator->nextRunAt($config)?->format('c'),
        );
    }

    public function test_preview_next_returns_multiple_cron_occurrences(): void
    {
        $config = ScheduleConfig::fromArray([
            'kind' => 'cron',
            'timezone' => 'UTC',
            'cron_expression' => '0 * * * *',
        ]);

        $occurrences = $this->calculator->previewNext(
            $config,
            3,
            new DateTimeImmutable('2026-07-18T10:15:00Z', new DateTimeZone('UTC')),
        );

        $this->assertSame([
            '2026-07-18T11:00:00+00:00',
            '2026-07-18T12:00:00+00:00',
            '2026-07-18T13:00:00+00:00',
        ], array_map(fn (DateTimeImmutable $dt) => $dt->format('c'), $occurrences));
    }

    public function test_preview_returns_next_n_occurrences(): void
    {
        $config = ScheduleConfig::fromArray([
            'kind' => 'every_n_minutes',
            'timezone' => 'UTC',
            'interval_minutes' => 10,
            'starts_at' => '2026-07-18T10:00:00Z',
        ]);

        $preview = $this->calculator->previewNext(
            $config,
            3,
            new DateTimeImmutable('2026-07-18T10:05:00Z', new DateTimeZone('UTC')),
        );

        $this->assertSame([
            '2026-07-18T10:10:00+00:00',
            '2026-07-18T10:20:00+00:00',
            '2026-07-18T10:30:00+00:00',
        ], array_map(static fn ($dt) => $dt->format('c'), $preview));
    }
}
