import type { ScheduleHuman } from '@/services/types'

type Translate = (key: string, params?: Record<string, unknown>) => string

/** Format API `schedule_human` ({kind,parts}) or a legacy string. */
export function formatScheduleHuman(
  value: ScheduleHuman | string | null | undefined,
  t: Translate,
): string {
  if (value == null || value === '') {
    return ''
  }
  if (typeof value === 'string') {
    return value
  }

  const kind = value.kind
  const parts = value.parts ?? {}
  const key = `tasks.scheduleHuman.${kind}`

  switch (kind) {
    case 'once':
      return t(key, { at: String(parts.at ?? ''), timezone: String(parts.timezone ?? '') })
    case 'every_n_minutes':
      return t(key, { minutes: Number(parts.interval_minutes ?? 0) })
    case 'hourly_at':
      return t(key, { minute: Number(parts.minute ?? 0) })
    case 'daily_at':
    case 'business_days_at':
      return t(key, {
        time: String(parts.time ?? ''),
        timezone: String(parts.timezone ?? ''),
      })
    case 'weekly_on': {
      const weekdays = Array.isArray(parts.weekdays)
        ? parts.weekdays.map((d) => t(`tasks.weekdays.${d}`)).join(', ')
        : ''
      return t(key, {
        weekdays,
        time: String(parts.time ?? ''),
        timezone: String(parts.timezone ?? ''),
      })
    }
    case 'monthly_on_day':
      return t(key, {
        day: Number(parts.day ?? 1),
        time: String(parts.time ?? ''),
        timezone: String(parts.timezone ?? ''),
      })
    case 'cron':
      return t(key, {
        expression: String(parts.cron_expression ?? ''),
        timezone: String(parts.timezone ?? ''),
      })
    default:
      return kind
  }
}

/** Mask an idempotency key for display (keep ends, hide middle). */
export function maskIdempotencyKey(key?: string | null): string {
  if (!key) {
    return '—'
  }
  if (key.length <= 12) {
    return key
  }
  return `${key.slice(0, 8)}…${key.slice(-4)}`
}
