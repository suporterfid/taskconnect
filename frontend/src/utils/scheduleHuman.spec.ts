import { describe, expect, it } from 'vitest'

import { formatScheduleHuman, maskIdempotencyKey } from './scheduleHuman'

describe('formatScheduleHuman', () => {
  const t = (key: string, params?: Record<string, unknown>) =>
    `${key}:${JSON.stringify(params ?? {})}`

  it('formats every_n_minutes payloads', () => {
    expect(
      formatScheduleHuman(
        { kind: 'every_n_minutes', parts: { interval_minutes: 15 } },
        t,
      ),
    ).toContain('every_n_minutes')
  })

  it('passes through legacy strings', () => {
    expect(formatScheduleHuman('Every 15 minutes', t)).toBe('Every 15 minutes')
  })
})

describe('maskIdempotencyKey', () => {
  it('masks long keys', () => {
    expect(maskIdempotencyKey('abcdefghijklmnop')).toBe('abcdefgh…mnop')
  })

  it('leaves short keys intact', () => {
    expect(maskIdempotencyKey('short')).toBe('short')
  })
})
