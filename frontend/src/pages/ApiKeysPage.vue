<script setup lang="ts">
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { ApiKey } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const tenant = useTenantStore()

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId) {
    return []
  }
  // API keys are tenant-scoped (not environment-scoped).
  const { data: response } = await api.get<{ data: ApiKey[] }>(
    `/tenants/${tenant.currentTenantId}/api-keys`,
  )
  return response.data ?? []
})
</script>

<template>
  <div>
    <PageHeader
      :title="$t('settings.apiKeys.title')"
      :subtitle="$t('settings.apiKeys.subtitle')"
    />

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('settings.apiKeys.loadError')"
      @retry="reload"
    />
    <div
      v-else-if="!data?.length"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('settings.apiKeys.empty') }}
    </div>
    <ul v-else class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
      <li v-for="key in data" :key="key.id" class="bg-white px-4 py-3 dark:bg-gray-900">
        <p class="font-medium">{{ key.name }}</p>
        <p class="font-mono text-sm text-gray-500">{{ key.prefix }}…</p>
      </li>
    </ul>
  </div>
</template>
