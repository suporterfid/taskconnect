import { beforeEach, describe, expect, it } from 'vitest'

import i18n, {
  FALLBACK_LOCALE,
  setLocale,
  SUPPORTED_LOCALES,
} from '@/i18n'

describe('i18n locale', () => {
  beforeEach(() => {
    localStorage.clear()
    setLocale('en')
    document.documentElement.lang = 'en'
  })

  it('supports en and pt-BR locales', () => {
    expect(SUPPORTED_LOCALES).toContain('en')
    expect(SUPPORTED_LOCALES).toContain('pt-BR')
  })

  it('switches locale and updates document lang', () => {
    setLocale('pt-BR')
    expect(i18n.global.locale.value).toBe('pt-BR')
    expect(document.documentElement.lang).toBe('pt-BR')
    expect(i18n.global.t('tasks.status.paused')).toBe('Pausada')
  })

  it('falls back to English for missing keys', () => {
    setLocale('pt-BR')
    expect(i18n.global.t('tasks.status.paused')).toBe('Pausada')
    setLocale('en')
    expect(i18n.global.t('tasks.status.paused')).toBe('Paused')
  })

  it('uses English as fallback locale', () => {
    expect(FALLBACK_LOCALE).toBe('en')
    setLocale('pt-BR')
    expect(i18n.global.t('common.appName')).toBe('TaskConnect')
  })

  it('persists locale to localStorage', () => {
    setLocale('pt-BR')
    expect(localStorage.getItem('locale')).toBe('pt-BR')
  })
})
