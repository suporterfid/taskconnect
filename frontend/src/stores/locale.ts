import { defineStore } from 'pinia'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

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

  return {
    currentLocale,
    supportedLocales: SUPPORTED_LOCALES,
    switchLocale,
  }
})
