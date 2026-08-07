<script setup lang="ts">
import { inject, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import {
  THEME_CONTROLLER_KEY,
  type ThemePreference,
} from '@/theme'

const { t } = useI18n()
const injectedController = inject(THEME_CONTROLLER_KEY)

if (!injectedController) {
  throw new Error('ThemeSelect requires the application theme controller')
}

const controller = injectedController

const preference = ref<ThemePreference>(controller.preference ?? 'system')
const unsubscribe = controller.subscribe((snapshot) => {
  preference.value = snapshot.preference ?? 'system'
})

onBeforeUnmount(unsubscribe)

function onChange(event: Event): void {
  const nextPreference = (event.target as HTMLSelectElement).value as ThemePreference
  controller.setPreference(nextPreference)
}
</script>

<template>
  <label
    data-theme-select
    class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-sm"
  >
    <span class="min-w-0 break-words text-muted">{{ t('common.theme.label') }}</span>
    <select
      :value="preference"
      :aria-label="t('common.theme.ariaLabel')"
      class="min-h-11 max-w-full min-w-0 rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-text"
      @change="onChange"
    >
      <option value="system">{{ t('common.theme.system') }}</option>
      <option value="light">{{ t('common.theme.light') }}</option>
      <option value="dark">{{ t('common.theme.dark') }}</option>
    </select>
  </label>
</template>
