<?php

namespace App\Domain\Scheduling;

use App\Domain\Shared\Clock;
use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeZone;

final class ScheduleCalculator
{
    public function __construct(
        private readonly Clock $clock,
    ) {
    }

    public function nextRunAt(ScheduleConfig $config, ?DateTimeImmutable $afterUtc = null): ?DateTimeImmutable
    {
        $afterUtc ??= $this->clock->nowUtc();

        $candidate = match ($config->kind) {
            ScheduleKind::Once => $this->nextOnce($config, $afterUtc),
            ScheduleKind::EveryNMinutes => $this->nextEveryNMinutes($config, $afterUtc),
            ScheduleKind::HourlyAt => $this->nextHourlyAt($config, $afterUtc),
            ScheduleKind::DailyAt => $this->nextDailyAt($config, $afterUtc),
            ScheduleKind::WeeklyOn => $this->nextWeeklyOn($config, $afterUtc),
            ScheduleKind::MonthlyOnDay => $this->nextMonthlyOnDay($config, $afterUtc),
            ScheduleKind::BusinessDaysAt => $this->nextBusinessDaysAt($config, $afterUtc),
            ScheduleKind::Cron => $this->nextCron($config, $afterUtc),
        };

        if ($candidate === null) {
            return null;
        }

        if ($config->startsAt !== null && $candidate < $config->startsAt) {
            return $this->nextRunAtAfterBound($config, $config->startsAt->modify('-1 second'));
        }

        if ($config->endsAt !== null && $candidate > $config->endsAt) {
            return null;
        }

        return $candidate;
    }

    /**
     * @return list<DateTimeImmutable>
     */
    public function previewNext(ScheduleConfig $config, int $count, ?DateTimeImmutable $afterUtc = null): array
    {
        if ($count < 1) {
            return [];
        }

        $occurrences = [];
        $cursor = $afterUtc ?? $this->clock->nowUtc();

        while (count($occurrences) < $count) {
            $next = $this->nextRunAt($config, $cursor);

            if ($next === null) {
                break;
            }

            $occurrences[] = $next;
            $cursor = $next;
        }

        return $occurrences;
    }

    private function nextRunAtAfterBound(ScheduleConfig $config, DateTimeImmutable $afterUtc): ?DateTimeImmutable
    {
        return match ($config->kind) {
            ScheduleKind::Once => $this->nextOnce($config, $afterUtc),
            ScheduleKind::EveryNMinutes => $this->nextEveryNMinutes($config, $afterUtc),
            ScheduleKind::HourlyAt => $this->nextHourlyAt($config, $afterUtc),
            ScheduleKind::DailyAt => $this->nextDailyAt($config, $afterUtc),
            ScheduleKind::WeeklyOn => $this->nextWeeklyOn($config, $afterUtc),
            ScheduleKind::MonthlyOnDay => $this->nextMonthlyOnDay($config, $afterUtc),
            ScheduleKind::BusinessDaysAt => $this->nextBusinessDaysAt($config, $afterUtc),
            ScheduleKind::Cron => $this->nextCron($config, $afterUtc),
        };
    }

    private function nextOnce(ScheduleConfig $config, DateTimeImmutable $afterUtc): ?DateTimeImmutable
    {
        if ($config->at === null) {
            return null;
        }

        return $config->at > $afterUtc ? $config->at : null;
    }

    private function nextEveryNMinutes(ScheduleConfig $config, DateTimeImmutable $afterUtc): ?DateTimeImmutable
    {
        $intervalMinutes = $config->intervalMinutes ?? 1;
        $intervalSeconds = $intervalMinutes * 60;
        $anchor = $config->startsAt ?? $afterUtc;

        if ($anchor > $afterUtc) {
            return $anchor;
        }

        $elapsed = $afterUtc->getTimestamp() - $anchor->getTimestamp();
        $periods = intdiv($elapsed, $intervalSeconds) + 1;

        return $anchor->modify(sprintf('+%d seconds', $periods * $intervalSeconds));
    }

    private function nextHourlyAt(ScheduleConfig $config, DateTimeImmutable $afterUtc): ?DateTimeImmutable
    {
        $timezone = new DateTimeZone($config->timezone);
        $afterLocal = $afterUtc->setTimezone($timezone);
        $minute = $config->minute ?? 0;

        $candidateLocal = $afterLocal
            ->setTime((int) $afterLocal->format('H'), $minute, 0);

        if ($candidateLocal <= $afterLocal) {
            $candidateLocal = $candidateLocal->modify('+1 hour');
        }

        return $candidateLocal->setTimezone(new DateTimeZone('UTC'));
    }

    private function nextDailyAt(ScheduleConfig $config, DateTimeImmutable $afterUtc): ?DateTimeImmutable
    {
        [$hour, $minute] = $this->parseTime($config->time ?? '00:00');
        $timezone = new DateTimeZone($config->timezone);
        $afterLocal = $afterUtc->setTimezone($timezone);

        for ($offsetDays = 0; $offsetDays < 366; $offsetDays++) {
            $date = $afterLocal->modify(sprintf('+%d days', $offsetDays));
            $candidate = $this->localDateTime(
                $timezone,
                (int) $date->format('Y'),
                (int) $date->format('m'),
                (int) $date->format('d'),
                $hour,
                $minute,
            );

            if ($candidate === null) {
                continue;
            }

            $candidateUtc = $candidate->setTimezone(new DateTimeZone('UTC'));

            if ($candidateUtc > $afterUtc) {
                return $candidateUtc;
            }
        }

        return null;
    }

    private function nextWeeklyOn(ScheduleConfig $config, DateTimeImmutable $afterUtc): ?DateTimeImmutable
    {
        [$hour, $minute] = $this->parseTime($config->time ?? '00:00');
        $timezone = new DateTimeZone($config->timezone);
        $weekdays = $config->weekdays ?? [];
        $afterLocal = $afterUtc->setTimezone($timezone);

        for ($offsetDays = 0; $offsetDays < 370; $offsetDays++) {
            $date = $afterLocal->modify(sprintf('+%d days', $offsetDays));
            $weekday = (int) $date->format('N');

            if (! in_array($weekday, $weekdays, true)) {
                continue;
            }

            $candidate = $this->localDateTime(
                $timezone,
                (int) $date->format('Y'),
                (int) $date->format('m'),
                (int) $date->format('d'),
                $hour,
                $minute,
            );

            if ($candidate === null) {
                continue;
            }

            $candidateUtc = $candidate->setTimezone(new DateTimeZone('UTC'));

            if ($candidateUtc > $afterUtc) {
                return $candidateUtc;
            }
        }

        return null;
    }

    private function nextMonthlyOnDay(ScheduleConfig $config, DateTimeImmutable $afterUtc): ?DateTimeImmutable
    {
        [$hour, $minute] = $this->parseTime($config->time ?? '00:00');
        $timezone = new DateTimeZone($config->timezone);
        $afterLocal = $afterUtc->setTimezone($timezone);
        $day = $config->dayOfMonth ?? 1;

        $year = (int) $afterLocal->format('Y');
        $month = (int) $afterLocal->format('m');

        for ($attempt = 0; $attempt < 36; $attempt++) {
            if (! checkdate($month, $day, $year)) {
                $month++;
                if ($month > 12) {
                    $month = 1;
                    $year++;
                }

                continue;
            }

            $candidate = $this->localDateTime($timezone, $year, $month, $day, $hour, $minute);

            if ($candidate === null) {
                $month++;
                if ($month > 12) {
                    $month = 1;
                    $year++;
                }

                continue;
            }

            $candidateUtc = $candidate->setTimezone(new DateTimeZone('UTC'));

            if ($candidateUtc > $afterUtc) {
                return $candidateUtc;
            }

            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
        }

        return null;
    }

    private function nextBusinessDaysAt(ScheduleConfig $config, DateTimeImmutable $afterUtc): ?DateTimeImmutable
    {
        [$hour, $minute] = $this->parseTime($config->time ?? '00:00');
        $timezone = new DateTimeZone($config->timezone);
        $afterLocal = $afterUtc->setTimezone($timezone);

        for ($offsetDays = 0; $offsetDays < 370; $offsetDays++) {
            $date = $afterLocal->modify(sprintf('+%d days', $offsetDays));
            $weekday = (int) $date->format('N');

            if ($weekday >= 6) {
                continue;
            }

            $candidate = $this->localDateTime(
                $timezone,
                (int) $date->format('Y'),
                (int) $date->format('m'),
                (int) $date->format('d'),
                $hour,
                $minute,
            );

            if ($candidate === null) {
                continue;
            }

            $candidateUtc = $candidate->setTimezone(new DateTimeZone('UTC'));

            if ($candidateUtc > $afterUtc) {
                return $candidateUtc;
            }
        }

        return null;
    }

    private function nextCron(ScheduleConfig $config, DateTimeImmutable $afterUtc): ?DateTimeImmutable
    {
        $expression = $config->cronExpression;
        if ($expression === null || $expression === '') {
            return null;
        }

        try {
            $cron = new CronExpression($expression);
        } catch (\Throwable) {
            return null;
        }

        $timezone = new DateTimeZone($config->timezone);
        $afterLocal = $afterUtc->setTimezone($timezone);

        try {
            $next = $cron->getNextRunDate($afterLocal, 0, false, $config->timezone);
        } catch (\Throwable) {
            return null;
        }

        $candidateUtc = DateTimeImmutable::createFromInterface($next)
            ->setTimezone(new DateTimeZone('UTC'));

        return $candidateUtc > $afterUtc ? $candidateUtc : null;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parseTime(string $time): array
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return [$hour, $minute];
    }

    private function localDateTime(
        DateTimeZone $timezone,
        int $year,
        int $month,
        int $day,
        int $hour,
        int $minute,
    ): ?DateTimeImmutable {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        $localString = sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute);
        $resolved = $this->resolveAmbiguousLocalTime($timezone, $localString);

        if ($resolved === null) {
            return $this->resolveSpringForwardGap($timezone, $year, $month, $day, $hour, $minute);
        }

        if ((int) $resolved->format('H') !== $hour || (int) $resolved->format('i') !== $minute) {
            return $this->resolveSpringForwardGap($timezone, $year, $month, $day, $hour, $minute) ?? $resolved;
        }

        return $resolved;
    }

    private function resolveAmbiguousLocalTime(DateTimeZone $timezone, string $localString): ?DateTimeImmutable
    {
        $resolved = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $localString, $timezone);

        if ($resolved === false) {
            return null;
        }

        $oneHourEarlierUtc = $resolved->getTimestamp() - 3600;
        $mappedEarlier = (new DateTimeImmutable('@'.$oneHourEarlierUtc))->setTimezone($timezone);

        if ($mappedEarlier->format('Y-m-d H:i:s') === $resolved->format('Y-m-d H:i:s')) {
            return (new DateTimeImmutable('@'.$oneHourEarlierUtc))->setTimezone($timezone);
        }

        return $resolved;
    }

    private function resolveSpringForwardGap(
        DateTimeZone $timezone,
        int $year,
        int $month,
        int $day,
        int $hour,
        int $minute,
    ): ?DateTimeImmutable {
        for ($probeMinute = ($hour * 60) + $minute; $probeMinute < 24 * 60; $probeMinute++) {
            $probeHour = intdiv($probeMinute, 60);
            $probeMin = $probeMinute % 60;

            if ($probeHour >= 24) {
                break;
            }

            $candidate = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $probeHour, $probeMin),
                $timezone,
            );

            if ($candidate !== false
                && (int) $candidate->format('H') === $probeHour
                && (int) $candidate->format('i') === $probeMin) {
                return $candidate;
            }
        }

        return null;
    }
}
