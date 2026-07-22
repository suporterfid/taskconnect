<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { PipelineInstance } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const props = defineProps<{ templateName: string; id: string }>()
const { t, locale } = useI18n()
const tenant = useTenantStore()

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    return null
  }
  const { data: response } = await api.get<{ data: PipelineInstance }>(
    tenant.tenantPath(`/pipelines/${props.templateName}/instances/${props.id}`),
  )
  return response.data
})

function formatDate(value?: string | null): string {
  if (!value) {
    return '—'
  }
  try {
    return new Intl.DateTimeFormat(locale.value, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(value))
  } catch {
    return value
  }
}

function statusLabel(status: string): string {
  return t(`pipelines.status.${status}`, status)
}
</script>

<template>
  <div data-testid="pipeline-detail-page">
    <div class="mb-4">
      <RouterLink to="/pipelines" class="text-sm text-violet-600 hover:underline">
        ← {{ $t('common.back') }}
      </RouterLink>
    </div>

    <LoadingState v-if="loading" />
    <ErrorState v-else-if="error" :message="error" @retry="reload" />
    <template v-else-if="data">
      <PageHeader
        :title="data.id"
        :subtitle="$t('pipelines.detail.title')"
        class="mb-6"
      />

      <dl class="mb-8 grid gap-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 sm:grid-cols-2">
        <div>
          <dt class="text-sm text-gray-500">{{ $t('pipelines.columns.template') }}</dt>
          <dd class="mt-1 text-sm font-medium">{{ data.template_name }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('pipelines.columns.status') }}</dt>
          <dd class="mt-1 text-sm">{{ statusLabel(data.status) }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('tasks.fields.workspaceId') }}</dt>
          <dd class="mt-1 font-mono text-sm">{{ data.workspace_id || '—' }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ $t('common.createdAt') }}</dt>
          <dd class="mt-1 text-sm">{{ formatDate(data.created_at) }}</dd>
        </div>
      </dl>

      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">
        {{ $t('pipelines.detail.nodes') }}
      </h2>
      <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $t('pipelines.detail.nodeKey') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $t('pipelines.detail.taskType') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $t('pipelines.detail.status') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $t('pipelines.detail.task') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $t('pipelines.detail.run') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-950">
            <tr v-for="node in data.nodes ?? []" :key="node.id">
              <td class="px-4 py-3 font-mono text-xs">{{ node.node_key }}</td>
              <td class="px-4 py-3 font-mono text-xs">{{ node.task_type }}</td>
              <td class="px-4 py-3 text-sm">{{ statusLabel(node.status) }}</td>
              <td class="px-4 py-3 text-sm">
                <RouterLink
                  v-if="node.task_id"
                  :to="`/tasks/${node.task_id}`"
                  class="text-violet-600 hover:underline"
                >
                  {{ node.task_id }}
                </RouterLink>
                <span v-else>—</span>
              </td>
              <td class="px-4 py-3 text-sm">
                <RouterLink
                  v-if="node.task_run_id"
                  :to="`/runs/${node.task_run_id}`"
                  class="text-violet-600 hover:underline"
                >
                  {{ node.task_run_id }}
                </RouterLink>
                <span v-else>—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
