import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import i18n, { setLocale } from '@/i18n'
import { useLocaleStore } from './locale'

const patchMock = vi.fn().mockResolvedValue({ data: {} })

vi.mock('@/services/api', () => ({
  default: {
    patch: (...args: unknown[]) => patchMock(...args),
  },
}))

function mountWithStore() {
  let store!: ReturnType<typeof useLocaleStore>

  const Harness = defineComponent({
    setup() {
      store = useLocaleStore()
      return () => h('div')
    },
  })

  mount(Harness, {
    global: {
      plugins: [i18n],
    },
  })

  return store
}

describe('locale store persistLocale', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    setLocale('en')
    patchMock.mockClear()
  })

  it('switches the locale immediately and persists it via PATCH /me/preferences', async () => {
    const store = mountWithStore()

    await store.persistLocale('pt-BR')

    expect(store.currentLocale).toBe('pt-BR')
    expect(patchMock).toHaveBeenCalledWith('/me/preferences', { locale: 'pt-BR' })
  })

  it('keeps the local switch even if persistence fails', async () => {
    patchMock.mockRejectedValueOnce(new Error('network down'))
    const store = mountWithStore()

    await store.persistLocale('pt-BR')

    expect(store.currentLocale).toBe('pt-BR')
  })
})
