<?php

namespace App\Domain\Scheduling;

enum ScheduleKind: string
{
    case Once = 'once';
    case EveryNMinutes = 'every_n_minutes';
    case HourlyAt = 'hourly_at';
    case DailyAt = 'daily_at';
    case WeeklyOn = 'weekly_on';
    case MonthlyOnDay = 'monthly_on_day';
    case BusinessDaysAt = 'business_days_at';
    /** Five-field cron expression (min hour dom month dow), evaluated in schedule timezone (R16). */
    case Cron = 'cron';
}
