<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { PipelineInstance, PipelineTemplate } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'
import { toneForPipelineInstanceStatus } from '@/utils/statusTone'

const { locale, t } = useI18n()
const tenant = useTenantStore()

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    return { templates: [] as PipelineTemplate[], instances: [] as PipelineInstance[] }
  }
  const [templatesRes, instancesRes] = await Promise.all([
    api.get<{ data: PipelineTemplate[] }>(tenant.tenantPath('/pipelines')),
    api.get<{ data: PipelineInstance[] }>(tenant.tenantPath('/pipeline-instances'), {
      params: { limit: 50 },
    }),
  ])
  return {
    templates: templatesRes.data.data ?? [],
    instances: instancesRes.data.data ?? [],
  }
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
  <div data-testid="pipelines-page">
    <PageHeader :title="$t('pipelines.title')" :subtitle="$t('pipelines.subtitle')" class="mb-8" />

    <LoadingState v-if="loading" />
    <ErrorState v-else-if="error" :message="error" @retry="reload" />
    <EmptyState v-else-if="!tenant.currentTenantId || !tenant.currentEnvironmentId" :message="$t('pipelines.needsTenant')" />
    <template v-else-if="data">
      <section class="mb-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted">
          {{ $t('pipelines.templates') }}
        </h2>
        <ul class="space-y-2">
          <li
            v-for="tpl in data.templates"
            :key="tpl.name"
            class="rounded-md border border-border px-4 py-3 text-sm"
          >
            <span class="font-medium text-text">{{ tpl.name }}</span>
            <span v-if="tpl.description" class="ms-2 text-muted">{{ tpl.description }}</span>
            <span class="mt-1 block font-mono text-xs text-muted">
              {{ tpl.nodes.map((n) => n.task_type).join(' → ') }}
            </span>
          </li>
        </ul>
      </section>

      <section>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted">
          {{ $t('pipelines.instances') }}
        </h2>
        <EmptyState v-if="!data.instances.length" :message="$t('pipelines.empty')" />
        <div v-else class="table-scroll" role="region" tabindex="0" :aria-label="$t('common.table.scrollRegion')">
          <table class="min-w-full divide-y divide-border">
            <thead class="bg-surface">
              <tr>
                <th class="px-4 py-3 text-start text-sm font-medium text-muted">{{ $t('pipelines.columns.id') }}</th>
                <th class="px-4 py-3 text-start text-sm font-medium text-muted">{{ $t('pipelines.columns.template') }}</th>
                <th class="px-4 py-3 text-start text-sm font-medium text-muted">{{ $t('pipelines.columns.status') }}</th>
                <th class="px-4 py-3 text-start text-sm font-medium text-muted">{{ $t('pipelines.columns.nodes') }}</th>
                <th class="px-4 py-3 text-start text-sm font-medium text-muted">{{ $t('pipelines.columns.created') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border bg-surface">
              <tr v-for="instance in data.instances" :key="instance.id">
                <td class="px-4 py-3 font-mono text-xs text-text">
                  <RouterLink
                    :to="`/pipelines/${instance.template_name}/instances/${instance.id}`"
                    class="link text-action-text"
                    data-testid="pipeline-instance-link"
                  >
                    {{ instance.id }}
                  </RouterLink>
                </td>
                <td class="px-4 py-3 text-sm text-text">{{ instance.template_name }}</td>
                <td class="px-4 py-3 text-sm">
                  <BaseBadge
                    :label="statusLabel(instance.status)"
                    :tone="toneForPipelineInstanceStatus(instance.status).tone"
                    :icon="toneForPipelineInstanceStatus(instance.status).icon"
                  />
                </td>
                <td class="px-4 py-3 text-sm tabular-nums text-muted">{{ instance.nodes?.length ?? 0 }}</td>
                <td class="px-4 py-3 text-sm tabular-nums text-muted">{{ formatDate(instance.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>
