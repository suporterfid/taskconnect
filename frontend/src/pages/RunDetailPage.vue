<script setup lang="ts">
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { Run } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const props = defineProps<{ id: string }>()
const tenant = useTenantStore()

const { data, loading, error, reload } = useAsyncData(async () => {
  const { data: response } = await api.get<{ data: Run }>(
    tenant.tenantPath(`/task-runs/${props.id}`),
  )
  return response.data
})
</script>

<template>
  <div>
    <div class="mb-4">
      <RouterLink to="/runs" class="text-sm text-violet-600 hover:underline">
        ← {{ $t('common.back') }}
      </RouterLink>
    </div>

    <LoadingState v-if="loading" />
    <ErrorState v-else-if="error" :message="error ?? $t('runs.loadError')" @retry="reload" />
    <template v-else-if="data">
      <PageHeader :title="data.id" :subtitle="$t('runs.detail.title')" />
      <dl class="grid gap-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 sm:grid-cols-2">
        <div>
          <dt class="text-sm text-gray-500">{{ $t('common.status') }}</dt>
          <dd class="mt-1">{{ $t(`runs.status.${data.status}`) }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('runs.detail.triggeredAt') }}</dt>
          <dd class="mt-1">{{ data.triggered_at }}</dd>
        </div>
      </dl>
    </template>
  </div>
</template>
