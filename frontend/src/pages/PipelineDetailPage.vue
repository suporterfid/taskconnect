<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { PipelineInstance } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'
import { toneForPipelineInstanceStatus, toneForPipelineNodeStatus } from '@/utils/statusTone'

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
      <RouterLink to="/pipelines" class="link text-sm text-action">
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

      <dl class="mb-8 grid gap-4 rounded-lg border border-border bg-surface p-6 sm:grid-cols-2">
        <div>
          <dt class="text-sm text-muted">{{ $t('pipelines.columns.template') }}</dt>
          <dd class="mt-1 text-sm font-medium text-text">{{ data.template_name }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('pipelines.columns.status') }}</dt>
          <dd class="mt-1 text-sm">
            <BaseBadge
              :label="statusLabel(data.status)"
              :tone="toneForPipelineInstanceStatus(data.status).tone"
              :icon="toneForPipelineInstanceStatus(data.status).icon"
            />
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('tasks.fields.workspaceId') }}</dt>
          <dd class="mt-1 font-mono text-sm text-text">{{ data.workspace_id || '—' }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('common.createdAt') }}</dt>
          <dd class="mt-1 text-sm tabular-nums text-text">{{ formatDate(data.created_at) }}</dd>
        </div>
      </dl>

      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted">
        {{ $t('pipelines.detail.nodes') }}
      </h2>
      <div class="overflow-x-auto rounded-lg border border-border">
        <table class="min-w-full divide-y divide-border">
          <thead class="bg-surface">
            <tr>
              <th class="px-4 py-3 text-left text-sm font-medium text-muted">{{ $t('pipelines.detail.nodeKey') }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-muted">{{ $t('pipelines.detail.taskType') }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-muted">{{ $t('pipelines.detail.status') }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-muted">{{ $t('pipelines.detail.task') }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-muted">{{ $t('pipelines.detail.run') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border bg-surface">
            <tr v-for="node in data.nodes ?? []" :key="node.id">
              <td class="px-4 py-3 font-mono text-xs text-text">{{ node.node_key }}</td>
              <td class="px-4 py-3 font-mono text-xs text-muted">{{ node.task_type }}</td>
              <td class="px-4 py-3 text-sm">
                <BaseBadge
                  :label="statusLabel(node.status)"
                  :tone="toneForPipelineNodeStatus(node.status).tone"
                  :icon="toneForPipelineNodeStatus(node.status).icon"
                />
              </td>
              <td class="px-4 py-3 text-sm">
                <RouterLink v-if="node.task_id" :to="`/tasks/${node.task_id}`" class="link text-action">
                  {{ node.task_id }}
                </RouterLink>
                <span v-else class="text-muted">—</span>
              </td>
              <td class="px-4 py-3 text-sm">
                <RouterLink v-if="node.task_run_id" :to="`/runs/${node.task_run_id}`" class="link text-action">
                  {{ node.task_run_id }}
                </RouterLink>
                <span v-else class="text-muted">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
