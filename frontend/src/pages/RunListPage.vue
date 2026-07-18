<script setup lang="ts">
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { Run } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const tenant = useTenantStore()

const { data, loading, error, reload } = useAsyncData(async () => {
  const { data: response } = await api.get<{ data: Run[] }>(
    tenant.tenantPath('/runs'),
  )
  return response.data ?? []
})
</script>

<template>
  <div>
    <PageHeader :title="$t('runs.title')" :subtitle="$t('runs.subtitle')" />

    <LoadingState v-if="loading" />
    <ErrorState v-else-if="error" :message="error ?? $t('runs.loadError')" @retry="reload" />
    <div
      v-else-if="!data?.length"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('runs.empty') }}
    </div>
    <ul v-else class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
      <li v-for="run in data" :key="run.id" class="flex items-center justify-between bg-white px-4 py-3 dark:bg-gray-900">
        <div>
          <p class="font-mono text-sm">{{ run.id }}</p>
          <p class="text-sm text-gray-500">{{ run.triggered_at }}</p>
        </div>
        <div class="flex items-center gap-4">
          <span class="text-sm">{{ $t(`runs.status.${run.status}`) }}</span>
          <RouterLink :to="`/runs/${run.id}`" class="text-sm text-violet-600 hover:underline">
            View
          </RouterLink>
        </div>
      </li>
    </ul>
  </div>
</template>
