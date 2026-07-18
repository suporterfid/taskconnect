<?php

namespace App\Domain\Scheduling;

final readonly class ScheduleDescription
{
    /**
     * @param  array<string, int|string|list<int|string>>  $parts
     */
    public function __construct(
        public ScheduleKind $kind,
        public array $parts,
    ) {
    }

    public static function fromConfig(ScheduleConfig $config): self
    {
        return match ($config->kind) {
            ScheduleKind::Once => new self(
                kind: $config->kind,
                parts: [
                    'at' => $config->at?->format('c') ?? '',
                    'timezone' => $config->timezone,
                ],
            ),
            ScheduleKind::EveryNMinutes => new self(
                kind: $config->kind,
                parts: [
                    'interval_minutes' => $config->intervalMinutes ?? 0,
                ],
            ),
            ScheduleKind::HourlyAt => new self(
                kind: $config->kind,
                parts: [
                    'minute' => $config->minute ?? 0,
                ],
            ),
            ScheduleKind::DailyAt => new self(
                kind: $config->kind,
                parts: [
                    'time' => $config->time ?? '00:00',
                    'timezone' => $config->timezone,
                ],
            ),
            ScheduleKind::WeeklyOn => new self(
                kind: $config->kind,
                parts: [
                    'weekdays' => $config->weekdays ?? [],
                    'time' => $config->time ?? '00:00',
                    'timezone' => $config->timezone,
                ],
            ),
            ScheduleKind::MonthlyOnDay => new self(
                kind: $config->kind,
                parts: [
                    'day' => $config->dayOfMonth ?? 1,
                    'time' => $config->time ?? '00:00',
                    'timezone' => $config->timezone,
                ],
            ),
            ScheduleKind::BusinessDaysAt => new self(
                kind: $config->kind,
                parts: [
                    'time' => $config->time ?? '00:00',
                    'timezone' => $config->timezone,
                ],
            ),
        };
    }
}
