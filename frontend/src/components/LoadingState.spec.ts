import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'

import LoadingState from './LoadingState.vue'
import enCommon from '@/i18n/locales/en/common.json'

function stubMatchMedia(reduced: boolean): void {
  window.matchMedia = vi.fn().mockImplementation((query: string) => ({
    matches: reduced && query === '(prefers-reduced-motion: reduce)',
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  }))
}

function mountLoadingState() {
  const i18n = createI18n({
    legacy: false,
    locale: 'en',
    messages: { en: { common: enCommon } },
  })
  return mount(LoadingState, { global: { plugins: [i18n] } })
}

describe('LoadingState', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('spins by default and preserves the status role and accessible label', () => {
    stubMatchMedia(false)
    const wrapper = mountLoadingState()

    expect(wrapper.find('[role="status"]').exists()).toBe(true)
    expect(wrapper.find('.animate-spin').exists()).toBe(true)
    expect(wrapper.find('.sr-only').text()).toBe('Loading…')
  })

  it('renders a static, non-animated indicator under prefers-reduced-motion', () => {
    stubMatchMedia(true)
    const wrapper = mountLoadingState()

    expect(wrapper.find('[role="status"]').exists()).toBe(true)
    expect(wrapper.find('.animate-spin').exists()).toBe(false)
    // Still a visible ring, not a frozen spinner arc: no transparent gap segment.
    expect(wrapper.find('.border-t-transparent').exists()).toBe(false)
    expect(wrapper.find('.sr-only').text()).toBe('Loading…')
  })
})
