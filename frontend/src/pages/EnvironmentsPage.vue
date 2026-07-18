<script setup lang="ts">
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { Environment } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const tenant = useTenantStore()

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId) {
    return []
  }
  const { data: response } = await api.get<{ data: Environment[] }>(
    `/tenants/${tenant.currentTenantId}/environments`,
  )
  return response.data ?? []
})
</script>

<template>
  <div>
    <PageHeader
      :title="$t('environments.title')"
      :subtitle="$t('environments.subtitle')"
    />

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('environments.loadError')"
      @retry="reload"
    />
    <div
      v-else-if="!data?.length"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('environments.empty') }}
    </div>
    <ul v-else class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
      <li v-for="env in data" :key="env.id" class="bg-white px-4 py-3 dark:bg-gray-900">
        <p class="font-medium">{{ env.name }}</p>
        <p class="text-sm text-gray-500">{{ env.slug }}</p>
      </li>
    </ul>
  </div>
</template>
