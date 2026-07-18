<script setup lang="ts">
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { PlatformHealth } from '@/services/types'

const { data, loading, error, reload } = useAsyncData(async () => {
  const { data: response } = await api.get<{ data: PlatformHealth }>(
    '/platform/health',
  )
  return response.data
})
</script>

<template>
  <div>
    <PageHeader
      :title="$t('settings.platformHealth.title')"
      :subtitle="$t('settings.platformHealth.subtitle')"
    />

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('settings.platformHealth.loadError')"
      @retry="reload"
    />
    <template v-else-if="data">
      <div
        class="mb-6 inline-flex rounded-full px-3 py-1 text-sm font-medium"
        :class="{
          'bg-green-100 text-green-800': data.status === 'ok',
          'bg-yellow-100 text-yellow-800': data.status === 'degraded',
          'bg-red-100 text-red-800': data.status === 'down',
        }"
      >
        {{ data.status }}
      </div>

      <ul class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
        <li
          v-for="(check, name) in data.checks"
          :key="name"
          class="flex items-center justify-between bg-white px-4 py-3 dark:bg-gray-900"
        >
          <span class="font-medium">{{ name }}</span>
          <span class="text-sm text-gray-500">{{ check.status }}</span>
        </li>
      </ul>
    </template>
  </div>
</template>
