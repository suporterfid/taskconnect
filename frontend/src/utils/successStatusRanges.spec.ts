import { describe, expect, it } from 'vitest'

import {
  formatSuccessStatusRanges,
  parseSuccessStatusRanges,
} from './successStatusRanges'

describe('successStatusRanges', () => {
  it('parses single codes and inclusive ranges', () => {
    expect(parseSuccessStatusRanges('204')).toEqual([[204, 204]])
    expect(parseSuccessStatusRanges('200-299, 404')).toEqual([
      [200, 299],
      [404, 404],
    ])
  })

  it('treats blank as null (default policy)', () => {
    expect(parseSuccessStatusRanges('')).toBeNull()
    expect(parseSuccessStatusRanges('   ')).toBeNull()
  })

  it('rejects invalid tokens', () => {
    expect(() => parseSuccessStatusRanges('ok')).toThrow(/Invalid/)
    expect(() => parseSuccessStatusRanges('200-100')).toThrow(/Invalid/)
  })

  it('formats ranges for the wizard input', () => {
    expect(formatSuccessStatusRanges([[200, 299], [404, 404]])).toBe(
      '200-299, 404',
    )
    expect(formatSuccessStatusRanges(null)).toBe('')
  })
})
