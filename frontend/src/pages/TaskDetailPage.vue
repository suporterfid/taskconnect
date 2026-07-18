<script setup lang="ts">
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { Task } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const props = defineProps<{ id: string }>()
const tenant = useTenantStore()

const { data, loading, error, reload } = useAsyncData(async () => {
  const { data: response } = await api.get<{ data: Task }>(
    tenant.tenantPath(`/tasks/${props.id}`),
  )
  return response.data
})
</script>

<template>
  <div>
    <div class="mb-4">
      <RouterLink to="/tasks" class="text-sm text-violet-600 hover:underline">
        ← {{ $t('common.back') }}
      </RouterLink>
    </div>

    <LoadingState v-if="loading" />
    <ErrorState v-else-if="error" :message="error ?? $t('tasks.loadError')" @retry="reload" />
    <template v-else-if="data">
      <PageHeader :title="data.name" :subtitle="$t('tasks.detail.title')" />

      <dl class="grid gap-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 sm:grid-cols-2">
        <div>
          <dt class="text-sm text-gray-500">{{ $t('common.status') }}</dt>
          <dd class="mt-1 text-sm font-medium">
            {{ $t(`tasks.status.${data.status}`) }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('common.description') }}</dt>
          <dd class="mt-1 text-sm">{{ data.description || '—' }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('common.createdAt') }}</dt>
          <dd class="mt-1 text-sm">{{ data.created_at }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('common.updatedAt') }}</dt>
          <dd class="mt-1 text-sm">{{ data.updated_at }}</dd>
        </div>
      </dl>

      <RouterLink
        :to="`/tasks/${id}/edit`"
        class="mt-4 inline-block rounded-md bg-violet-600 px-4 py-2 text-sm text-white hover:bg-violet-700"
      >
        {{ $t('common.edit') }}
      </RouterLink>
    </template>
  </div>
</template>
