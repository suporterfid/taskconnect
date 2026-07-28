import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import axe from 'axe-core'
import { createI18n } from 'vue-i18n'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createPinia, setActivePinia } from 'pinia'

import LoginPage from '@/pages/LoginPage.vue'
import enAuth from '@/i18n/locales/en/auth.json'
import enCommon from '@/i18n/locales/en/common.json'

describe('a11y smoke', () => {
  it('login page has no serious axe violations', async () => {
    setActivePinia(createPinia())
    const i18n = createI18n({
      legacy: false,
      locale: 'en',
      messages: { en: { auth: enAuth, common: enCommon } },
    })
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/login', component: LoginPage }],
    })
    await router.push('/login')
    await router.isReady()

    const wrapper = mount(LoginPage, {
      global: {
        plugins: [i18n, router],
      },
      attachTo: document.body,
    })

    const results = await axe.run(wrapper.element, {
      rules: {
        // jsdom can't compute real color contrast — frontend/e2e/a11y.spec.ts (#98)
        // runs the same axe ruleset in a real Chromium page with this rule enabled,
        // which is the actual contrast check for this app. Kept disabled here rather
        // than removed so this spec still documents *why* it's not the source of truth
        // for that one rule.
        'color-contrast': { enabled: false },
      },
    })

    const serious = results.violations.filter((v) =>
      ['serious', 'critical'].includes(v.impact ?? ''),
    )
    expect(serious, JSON.stringify(serious, null, 2)).toEqual([])
    wrapper.unmount()
  })
})
