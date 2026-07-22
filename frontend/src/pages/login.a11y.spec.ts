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
        // jsdom lacks full color contrast computation reliability
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
