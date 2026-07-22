<?php

namespace App\Domain\Scheduling;

use DateTimeImmutable;
use DateTimeZone;

final readonly class ScheduleConfig
{
    /**
     * @param  list<int>|null  $weekdays ISO-8601 weekday numbers (1=Mon..7=Sun)
     */
    public function __construct(
        public ScheduleKind $kind,
        public string $timezone,
        public ?DateTimeImmutable $at = null,
        public ?int $intervalMinutes = null,
        public ?int $minute = null,
        public ?string $time = null,
        public ?array $weekdays = null,
        public ?int $dayOfMonth = null,
        public ?string $cronExpression = null,
        public ?DateTimeImmutable $startsAt = null,
        public ?DateTimeImmutable $endsAt = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['kind'], $data['timezone'])) {
            throw new InvalidScheduleConfigException('Schedule kind and timezone are required.');
        }

        $kind = ScheduleKind::tryFrom((string) $data['kind']);

        if ($kind === null) {
            throw new InvalidScheduleConfigException(sprintf('Unknown schedule kind "%s".', $data['kind']));
        }

        try {
            new DateTimeZone((string) $data['timezone']);
        } catch (\Exception) {
            throw new InvalidScheduleConfigException(sprintf('Invalid timezone "%s".', $data['timezone']));
        }

        $startsAt = self::parseOptionalUtcTimestamp($data['starts_at'] ?? null);
        $endsAt = self::parseOptionalUtcTimestamp($data['ends_at'] ?? null);

        return match ($kind) {
            ScheduleKind::Once => self::fromOnce($data, $startsAt, $endsAt),
            ScheduleKind::EveryNMinutes => self::fromEveryNMinutes($data, $startsAt, $endsAt),
            ScheduleKind::HourlyAt => self::fromHourlyAt($data, $startsAt, $endsAt),
            ScheduleKind::DailyAt => self::fromDailyAt($data, $startsAt, $endsAt),
            ScheduleKind::WeeklyOn => self::fromWeeklyOn($data, $startsAt, $endsAt),
            ScheduleKind::MonthlyOnDay => self::fromMonthlyOnDay($data, $startsAt, $endsAt),
            ScheduleKind::BusinessDaysAt => self::fromBusinessDaysAt($data, $startsAt, $endsAt),
            ScheduleKind::Cron => self::fromCron($data, $startsAt, $endsAt),
        };
    }

    /**
     * Build a one-shot delayed schedule from a top-level run_at (R16).
     */
    public static function delayedOnce(string $runAt, string $timezone = 'UTC'): self
    {
        return self::fromArray([
            'kind' => ScheduleKind::Once->value,
            'timezone' => $timezone,
            'at' => $runAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function fromOnce(array $data, ?DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt): self
    {
        if (! isset($data['at'])) {
            throw new InvalidScheduleConfigException('One-time schedules require an "at" timestamp.');
        }

        $at = self::parseRequiredTimestamp((string) $data['at'], (string) $data['timezone']);

        return new self(
            kind: ScheduleKind::Once,
            timezone: (string) $data['timezone'],
            at: $at,
            startsAt: $startsAt,
            endsAt: $endsAt,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function fromEveryNMinutes(array $data, ?DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt): self
    {
        $interval = $data['interval_minutes'] ?? $data['interval'] ?? null;

        if (! is_int($interval) && ! (is_string($interval) && ctype_digit($interval))) {
            throw new InvalidScheduleConfigException('Every-N-minutes schedules require a positive interval.');
        }

        $interval = (int) $interval;

        if ($interval < 1) {
            throw new InvalidScheduleConfigException('Interval minutes must be at least 1.');
        }

        return new self(
            kind: ScheduleKind::EveryNMinutes,
            timezone: (string) $data['timezone'],
            intervalMinutes: $interval,
            startsAt: $startsAt,
            endsAt: $endsAt,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function fromHourlyAt(array $data, ?DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt): self
    {
        if (! isset($data['minute'])) {
            throw new InvalidScheduleConfigException('Hourly schedules require a minute offset.');
        }

        $minute = (int) $data['minute'];

        if ($minute < 0 || $minute > 59) {
            throw new InvalidScheduleConfigException('Hourly minute must be between 0 and 59.');
        }

        return new self(
            kind: ScheduleKind::HourlyAt,
            timezone: (string) $data['timezone'],
            minute: $minute,
            startsAt: $startsAt,
            endsAt: $endsAt,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function fromDailyAt(array $data, ?DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt): self
    {
        self::assertTime((string) ($data['time'] ?? ''), 'Daily schedules require a valid time.');

        return new self(
            kind: ScheduleKind::DailyAt,
            timezone: (string) $data['timezone'],
            time: (string) $data['time'],
            startsAt: $startsAt,
            endsAt: $endsAt,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function fromWeeklyOn(array $data, ?DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt): self
    {
        self::assertTime((string) ($data['time'] ?? ''), 'Weekly schedules require a valid time.');

        $weekdays = $data['weekdays'] ?? null;

        if (! is_array($weekdays) || $weekdays === []) {
            throw new InvalidScheduleConfigException('Weekly schedules require at least one weekday.');
        }

        $normalized = [];

        foreach ($weekdays as $weekday) {
            $value = (int) $weekday;

            if ($value < 1 || $value > 7) {
                throw new InvalidScheduleConfigException('Weekdays must use ISO-8601 values from 1 to 7.');
            }

            $normalized[] = $value;
        }

        sort($normalized);

        return new self(
            kind: ScheduleKind::WeeklyOn,
            timezone: (string) $data['timezone'],
            time: (string) $data['time'],
            weekdays: array_values(array_unique($normalized)),
            startsAt: $startsAt,
            endsAt: $endsAt,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function fromMonthlyOnDay(array $data, ?DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt): self
    {
        self::assertTime((string) ($data['time'] ?? ''), 'Monthly schedules require a valid time.');

        if (! isset($data['day'])) {
            throw new InvalidScheduleConfigException('Monthly schedules require a day of month.');
        }

        $day = (int) $data['day'];

        if ($day < 1 || $day > 31) {
            throw new InvalidScheduleConfigException('Monthly day must be between 1 and 31.');
        }

        return new self(
            kind: ScheduleKind::MonthlyOnDay,
            timezone: (string) $data['timezone'],
            time: (string) $data['time'],
            dayOfMonth: $day,
            startsAt: $startsAt,
            endsAt: $endsAt,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function fromBusinessDaysAt(array $data, ?DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt): self
    {
        self::assertTime((string) ($data['time'] ?? ''), 'Business-day schedules require a valid time.');

        return new self(
            kind: ScheduleKind::BusinessDaysAt,
            timezone: (string) $data['timezone'],
            time: (string) $data['time'],
            startsAt: $startsAt,
            endsAt: $endsAt,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function fromCron(array $data, ?DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt): self
    {
        $expression = $data['cron_expression'] ?? $data['expression'] ?? null;
        if (! is_string($expression) || trim($expression) === '') {
            throw new InvalidScheduleConfigException('Cron schedules require a cron_expression.');
        }

        $expression = trim($expression);
        $parts = preg_split('/\s+/', $expression) ?: [];
        if (count($parts) !== 5) {
            throw new InvalidScheduleConfigException('Cron expression must have exactly 5 fields (min hour dom month dow).');
        }

        try {
            new \Cron\CronExpression($expression);
        } catch (\Throwable $exception) {
            throw new InvalidScheduleConfigException(
                'Invalid cron expression: '.$exception->getMessage(),
                0,
                $exception,
            );
        }

        return new self(
            kind: ScheduleKind::Cron,
            timezone: (string) $data['timezone'],
            cronExpression: $expression,
            startsAt: $startsAt,
            endsAt: $endsAt,
        );
    }

    private static function assertTime(string $time, string $message): void
    {
        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new InvalidScheduleConfigException($message);
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));

        if ($hour > 23 || $minute > 59) {
            throw new InvalidScheduleConfigException($message);
        }
    }

    private static function parseOptionalUtcTimestamp(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    }

    private static function parseRequiredTimestamp(string $value, string $timezone): DateTimeImmutable
    {
        $hasOffset = str_contains($value, 'Z')
            || preg_match('/[+-]\d{2}:\d{2}$/', $value) === 1;

        if ($hasOffset) {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
        }

        return (new DateTimeImmutable($value, new DateTimeZone($timezone)))->setTimezone(new DateTimeZone('UTC'));
    }
}
