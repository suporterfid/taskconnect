import { describe, expect, it } from 'vitest'

import {
  compareLocalized,
  formatDateTime,
  formatNumber,
  formatUnit,
} from './format'

describe('locale-aware formatting', () => {
  it('formats dates and numbers deterministically for English and Brazilian Portuguese', () => {
    const date = '2026-08-07T13:45:00Z'
    expect(formatDateTime(date, 'en', 'UTC')).toBe('Aug 7, 2026, 1:45 PM')
    expect(formatDateTime(date, 'pt-BR', 'UTC')).toBe('7 de ago. de 2026, 13:45')
    expect(formatNumber(12345.6, 'en')).toBe('12,345.6')
    expect(formatNumber(12345.6, 'pt-BR')).toBe('12.345,6')
  })

  it('sorts with the selected locale collator', () => {
    const values = ['Zulu', 'Árvore', 'abacaxi']
    expect([...values].sort((a, b) => compareLocalized(a, b, 'pt-BR'))).toEqual([
      'abacaxi',
      'Árvore',
      'Zulu',
    ])
  })

  it('uses translated plural units with locale-formatted counts', () => {
    const translate = (key: string, named: Record<string, unknown>) => {
      const count = String(named.formattedCount)
      const raw = Number(named.rawCount)
      if (key === 'common.units.day') return raw === 1 ? `${count} day` : `${count} days`
      return raw === 1 ? `${count} hora` : `${count} horas`
    }
    expect(formatUnit(1, 'day', 'en', translate)).toBe('1 day')
    expect(formatUnit(2, 'day', 'en', translate)).toBe('2 days')
    expect(formatUnit(1000, 'hour', 'pt-BR', translate)).toBe('1.000 horas')
  })

  it('localizes seconds and milliseconds in English and Brazilian Portuguese', () => {
    const messages = {
      en: {
        second: ['second', 'seconds'],
        millisecond: ['millisecond', 'milliseconds'],
      },
      'pt-BR': {
        second: ['segundo', 'segundos'],
        millisecond: ['milissegundo', 'milissegundos'],
      },
    } as const
    const translateFor = (selectedLocale: keyof typeof messages) =>
      (key: string, named: Record<string, unknown>) => {
        const unit = key.split('.').at(-1) as keyof (typeof messages)[typeof selectedLocale]
        const raw = Number(named.rawCount)
        return `${String(named.formattedCount)} ${messages[selectedLocale][unit][raw === 1 ? 0 : 1]}`
      }

    expect(formatUnit(1, 'second', 'en', translateFor('en'))).toBe('1 second')
    expect(formatUnit(2, 'second', 'pt-BR', translateFor('pt-BR'))).toBe('2 segundos')
    expect(formatUnit(1000, 'millisecond', 'en', translateFor('en'))).toBe('1,000 milliseconds')
    expect(formatUnit(1000, 'millisecond', 'pt-BR', translateFor('pt-BR'))).toBe('1.000 milissegundos')
  })
})
