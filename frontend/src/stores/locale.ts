import { defineStore } from 'pinia'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import api from '@/services/api'
import {
  SUPPORTED_LOCALES,
  setLocale,
  type SupportedLocale,
} from '@/i18n'

export const useLocaleStore = defineStore('locale', () => {
  const { locale } = useI18n()

  const currentLocale = computed(() => locale.value as SupportedLocale)

  function switchLocale(next: SupportedLocale): void {
    if (!SUPPORTED_LOCALES.includes(next)) {
      return
    }
    setLocale(next)
  }

  /**
   * Switches the locale immediately (optimistic) and persists it to the
   * backend so it survives a reload. Failure is non-fatal — the switch
   * already took effect locally.
   */
  async function persistLocale(next: SupportedLocale): Promise<void> {
    switchLocale(next)
    try {
      await api.patch('/me/preferences', { locale: next })
    } catch (err) {
      console.warn('Failed to persist locale preference:', err)
    }
  }

  return {
    currentLocale,
    supportedLocales: SUPPORTED_LOCALES,
    switchLocale,
    persistLocale,
  }
})
