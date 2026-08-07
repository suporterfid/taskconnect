import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'

import i18n, { setLocale } from '@/i18n'
import {
  createThemeController,
  THEME_CONTROLLER_KEY,
  THEME_STORAGE_KEY,
  type ThemeController,
} from '@/theme'
import ThemeSelect from './ThemeSelect.vue'

class MediaQueryStub extends EventTarget {
  matches = false
}

function mountThemeSelect(messages?: Record<string, string>) {
  if (messages) {
    i18n.global.mergeLocaleMessage('en', {
      common: {
        theme: {
          label: messages['common.theme.label'],
          ariaLabel: messages['common.theme.ariaLabel'],
        },
      },
    })
  }
  const storage = window.localStorage
  const controller = createThemeController({
    document,
    media: new MediaQueryStub(),
    storage,
  })

  const wrapper = mount(ThemeSelect, {
    global: {
      plugins: [i18n],
      provide: { [THEME_CONTROLLER_KEY as symbol]: controller },
    },
  })

  return { controller, wrapper }
}

describe('ThemeSelect', () => {
  let controller: ThemeController | undefined

  beforeEach(() => {
    controller?.destroy()
    controller = undefined
    localStorage.clear()
    i18n.global.mergeLocaleMessage('en', {
      common: {
        theme: {
          label: 'Theme',
          ariaLabel: 'Theme preference',
        },
      },
    })
    setLocale('en')
  })

  it('uses one native labelled select with the exact three preferences', () => {
    const mounted = mountThemeSelect()
    controller = mounted.controller

    const label = mounted.wrapper.get('label')
    const select = mounted.wrapper.get('select')
    expect(label.text()).toContain('Theme')
    expect(select.attributes('aria-label')).toBe('Theme preference')
    expect(select.findAll('option').map((option) => option.attributes('value'))).toEqual([
      'system',
      'light',
      'dark',
    ])
    expect(select.findAll('option').map((option) => option.text())).toEqual([
      'Use system setting',
      'Light',
      'Dark',
    ])
  })

  it('sets and persists the preference through the shared controller', async () => {
    const mounted = mountThemeSelect()
    controller = mounted.controller

    await mounted.wrapper.get('select').setValue('dark')

    expect(controller.preference).toBe('dark')
    expect(localStorage.getItem(THEME_STORAGE_KEY)).toBe('dark')
    expect(document.documentElement.dataset.theme).toBe('dark')
  })

  it('renders the Portuguese message catalog without translating autonyms', () => {
    setLocale('pt-BR')
    const mounted = mountThemeSelect()
    controller = mounted.controller

    expect(mounted.wrapper.get('label').text()).toContain('Tema')
    expect(mounted.wrapper.get('select').attributes('aria-label')).toBe('Preferência de tema')
    expect(mounted.wrapper.findAll('option').map((option) => option.text())).toEqual([
      'Usar configuração do sistema',
      'Claro',
      'Escuro',
    ])
    expect(i18n.global.t('common.locale.en')).toBe('English')
    expect(i18n.global.t('common.locale.pt-BR')).toBe('Português (Brasil)')
  })

  it('allows a two-times pseudo-localized label to wrap around a 44px native target', () => {
    const longLabel = '[Ŧħḗḿḗ ƥřḗƒḗřḗƞƈḗ · Ŧħḗḿḗ ƥřḗƒḗřḗƞƈḗ]'
    const mounted = mountThemeSelect({
      'common.theme.label': longLabel,
      'common.theme.ariaLabel': longLabel,
    })
    controller = mounted.controller

    expect(mounted.wrapper.get('[data-theme-select]').classes()).toContain('flex-wrap')
    expect(mounted.wrapper.get('select').classes()).toContain('min-h-11')
    expect(mounted.wrapper.get('select').attributes('aria-label')).toBe(longLabel)
    expect(mounted.wrapper.text()).toContain(longLabel)
  })
})
