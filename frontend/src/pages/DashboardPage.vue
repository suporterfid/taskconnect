<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { DashboardStats } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const { t } = useI18n()
const tenant = useTenantStore()

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    return {
      active_tasks: 0,
      paused_tasks: 0,
      recent_runs: 0,
      failed_runs_24h: 0,
    } satisfies DashboardStats
  }

  const path = tenant.tenantPath('/dashboard')
  const { data: response } = await api.get<{ data: DashboardStats }>(path)
  return response.data
})

const needsTenant = computed(
  () => !tenant.currentTenantId || !tenant.currentEnvironmentId,
)

const stats = computed(() => [
  { label: t('dashboard.stats.activeTasks'), value: data.value?.active_tasks ?? 0 },
  { label: t('dashboard.stats.pausedTasks'), value: data.value?.paused_tasks ?? 0 },
  { label: t('dashboard.stats.recentRuns'), value: data.value?.recent_runs ?? 0 },
  { label: t('dashboard.stats.failedRuns'), value: data.value?.failed_runs_24h ?? 0 },
])
</script>

<template>
  <div>
    <PageHeader :title="$t('dashboard.title')" :subtitle="$t('dashboard.subtitle')" />

    <LoadingState v-if="loading" />
    <ErrorState v-else-if="error" :message="error" @retry="reload" />
    <div
      v-else-if="needsTenant"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('dashboard.needsTenant') }}
    </div>
    <template v-else>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="stat in stats"
          :key="stat.label"
          class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
          <p class="text-sm text-gray-500">{{ stat.label }}</p>
          <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-gray-100">
            {{ stat.value }}
          </p>
        </div>
      </div>
      <p
        v-if="stats.every((s) => s.value === 0)"
        class="mt-8 text-center text-gray-500"
      >
        {{ $t('dashboard.empty') }}
      </p>
    </template>
  </div>
</template>
