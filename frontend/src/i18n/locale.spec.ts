import { beforeEach, describe, expect, it } from 'vitest'

import i18n, {
  documentDirectionForLocale,
  FALLBACK_LOCALE,
  setLocale,
  SUPPORTED_LOCALES,
  updateDocumentLang,
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
    expect(document.documentElement.dir).toBe('ltr')
    expect(i18n.global.t('tasks.status.paused')).toBe('Pausada')
  })

  it.each([
    ['ar-XB', 'rtl'],
    ['ar', 'rtl'],
    ['he-IL', 'rtl'],
    ['fa-IR', 'rtl'],
    ['ur-PK', 'rtl'],
    ['zh-Hans', 'ltr'],
    ['ja-JP', 'ltr'],
    ['en-XA', 'ltr'],
    ['el-GR', 'ltr'],
  ])('resolves %s to %s document direction', (locale, expected) => {
    expect(documentDirectionForLocale(locale)).toBe(expected)
  })

  it('updates lang and dir together for synthetic test locales', () => {
    updateDocumentLang('ar-XB')
    expect(document.documentElement.lang).toBe('ar-XB')
    expect(document.documentElement.dir).toBe('rtl')

    updateDocumentLang('zh-Hans')
    expect(document.documentElement.lang).toBe('zh-Hans')
    expect(document.documentElement.dir).toBe('ltr')
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
