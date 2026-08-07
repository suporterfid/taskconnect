import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, describe, expect, it, vi } from 'vitest'

import i18n from '@/i18n'
import { createThemeController, THEME_CONTROLLER_KEY } from '@/theme'
import AppLayout from './AppLayout.vue'

const layoutSource = readFileSync(
  join(dirname(fileURLToPath(import.meta.url)), 'AppLayout.vue'),
  'utf8',
)

class ResponsiveMediaStub extends EventTarget {
  readonly media = '(max-width: 767px)'
  matches: boolean

  constructor(matches: boolean) {
    super()
    this.matches = matches
  }

  setMobile(matches: boolean): void {
    this.matches = matches
    this.dispatchEvent(new Event('change'))
  }
}

async function mountLayout(media: ResponsiveMediaStub) {
  vi.stubGlobal('matchMedia', () => media)
  const themeController = createThemeController({ document, media, storage: localStorage })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div>Page</div>' } }],
  })
  await router.push('/dashboard')
  await router.isReady()

  return mount(AppLayout, {
    attachTo: document.body,
    global: {
      plugins: [createPinia(), i18n, router],
      provide: { [THEME_CONTROLLER_KEY as symbol]: themeController },
    },
  })
}

afterEach(() => {
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

describe('AppLayout structural directionality contract', () => {
  it('does not use physical directional Tailwind utilities', () => {
    const forbidden = [
      /(?:^|[\s:'"])(?:left|right)-\S+/,
      /(?:^|[\s:'"])(?:ml|mr|pl|pr)-\S+/,
      /(?:^|[\s:'"])(?:border-(?:l|r))(?:-|\b)/,
      /(?:^|[\s:'"])(?:text-(?:left|right))(?:\s|'|")/,
      /(?:^|[\s:'"])(?:-?translate-x-\S+)/,
    ]

    for (const pattern of forbidden) {
      expect(layoutSource, `physical utility matched ${pattern}`).not.toMatch(pattern)
    }
  })

  it('maps asymmetric safe areas and closes the sidebar toward logical inline-start', () => {
    expect(layoutSource).toContain('--safe-inline-start: env(safe-area-inset-left)')
    expect(layoutSource).toContain('--safe-inline-end: env(safe-area-inset-right)')
    expect(layoutSource).toContain('--safe-block-start: env(safe-area-inset-top)')
    expect(layoutSource).toContain('--safe-block-end: env(safe-area-inset-bottom)')
    expect(layoutSource).toContain(":global([dir='rtl']) .app-shell")
    expect(layoutSource).toContain('--safe-inline-start: env(safe-area-inset-right)')
    expect(layoutSource).toContain('inset-inline-start: 0')
    expect(layoutSource).toContain('border-inline-end')
    expect(layoutSource).toContain(":global([dir='rtl']) .app-sidebar--closed")
    expect(layoutSource).toMatch(/\.app-sidebar\s*\{[^}]*padding-block-start:[^}]*--safe-block-start/s)
    expect(layoutSource).toMatch(/\.app-sidebar\s*\{[^}]*padding-block-end:[^}]*--safe-block-end/s)
    expect(layoutSource).toMatch(/\.app-header\s*\{[^}]*padding-block-start:[^}]*--safe-block-start/s)
    expect(layoutSource).toMatch(/\.app-main\s*\{[^}]*padding-block-end:[^}]*--safe-block-end/s)
  })

  it('removes the closed mobile sidebar from focus navigation and restores it when open or desktop', async () => {
    const media = new ResponsiveMediaStub(true)
    const wrapper = await mountLayout(media)
    const sidebar = wrapper.get('#app-sidebar')
    const nav = sidebar.get('nav')

    expect(sidebar.attributes('inert')).toBeDefined()
    expect(sidebar.attributes('aria-hidden')).toBe('true')
    expect(nav.attributes('tabindex')).toBe('0')
    expect(nav.classes()).toContain('min-h-0')
    expect(nav.classes()).toContain('overflow-y-auto')

    await wrapper.get('button[aria-controls="app-sidebar"]').trigger('click')
    expect(sidebar.attributes('inert')).toBeUndefined()
    expect(sidebar.attributes('aria-hidden')).toBeUndefined()

    await wrapper.get('button[aria-label="Close navigation"]').trigger('click')
    expect(sidebar.attributes('inert')).toBeDefined()

    media.setMobile(false)
    await wrapper.vm.$nextTick()
    expect(sidebar.attributes('inert')).toBeUndefined()
    expect(sidebar.attributes('aria-hidden')).toBeUndefined()

    wrapper.unmount()
  })

  it('uses one localized theme control and 44px shell targets', () => {
    expect(layoutSource).toContain('<ThemeSelect')
    expect(layoutSource.match(/<ThemeSelect/g)).toHaveLength(1)
    expect(layoutSource).toContain('min-h-11')
    expect(layoutSource).toContain('min-w-11')
  })
})
