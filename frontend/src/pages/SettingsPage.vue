<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import PageHeader from '@/components/PageHeader.vue'
import type { SupportedLocale } from '@/i18n'
import { useLocaleStore } from '@/stores/locale'

const { t } = useI18n()
const localeStore = useLocaleStore()

const note = ref<string | null>(null)

function onLocaleChange(event: Event): void {
  const locale = (event.target as HTMLSelectElement).value as SupportedLocale
  localeStore.switchLocale(locale)
  // TODO: PATCH /me/preferences when the preferences API exists (sibling agent).
  // For now sync locale locally via the locale store + localStorage only.
  note.value = t('settings.locale.savedLocally')
}
</script>

<template>
  <div>
    <PageHeader :title="$t('settings.title')" :subtitle="$t('settings.subtitle')" />

    <div class="space-y-6">
      <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="text-lg font-medium">{{ $t('settings.sections.locale') }}</h2>
        <p class="mt-2 text-sm text-gray-500">
          {{ $t('settings.locale.hint') }}
        </p>

        <label class="mt-4 flex max-w-xs flex-col gap-1 text-sm">
          <span class="font-medium text-gray-700">{{ $t('common.locale.label') }}</span>
          <select
            class="rounded-md border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
            :value="localeStore.currentLocale"
            @change="onLocaleChange"
          >
            <option value="en">{{ $t('common.locale.en') }}</option>
            <option value="pt-BR">{{ $t('common.locale.pt-BR') }}</option>
          </select>
        </label>

        <p v-if="note" class="mt-3 text-sm text-green-700" role="status">
          {{ note }}
        </p>
      </section>

      <!-- TODO: Audit log list when GET /tenants/{id}/audit-logs (or similar) is available. -->
    </div>
  </div>
</template>
