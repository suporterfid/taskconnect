export type TranslationFunction = (
  key: string,
  named: Record<string, unknown>,
) => string

export type LocalizedUnit = 'day' | 'hour' | 'minute' | 'second' | 'millisecond'

export function formatDateTime(
  value: string | Date,
  locale: string,
  timeZone?: string,
  timeStyle: 'short' | 'medium' = 'short',
): string {
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)

  return new Intl.DateTimeFormat(locale, {
    dateStyle: 'medium',
    timeStyle,
    ...(timeZone ? { timeZone } : {}),
  }).format(date)
}

export function formatNumber(value: number, locale: string): string {
  return new Intl.NumberFormat(locale).format(value)
}

export function compareLocalized(first: string, second: string, locale: string): number {
  return new Intl.Collator(locale, { sensitivity: 'base', numeric: true }).compare(first, second)
}

export function formatUnit(
  value: number,
  unit: LocalizedUnit,
  locale: string,
  translate: TranslationFunction,
): string {
  return translate(`common.units.${unit}`, {
    count: value,
    formattedCount: formatNumber(value, locale),
    rawCount: value,
  })
}
