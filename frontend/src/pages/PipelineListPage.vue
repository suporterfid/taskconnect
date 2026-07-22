<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { PipelineInstance, PipelineTemplate } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

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
    <div
      v-else-if="!tenant.currentTenantId || !tenant.currentEnvironmentId"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('pipelines.needsTenant') }}
    </div>
    <template v-else-if="data">
      <section class="mb-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">
          {{ $t('pipelines.templates') }}
        </h2>
        <ul class="space-y-2">
          <li
            v-for="tpl in data.templates"
            :key="tpl.name"
            class="rounded-md border border-gray-200 px-4 py-3 text-sm dark:border-gray-800"
          >
            <span class="font-medium">{{ tpl.name }}</span>
            <span v-if="tpl.description" class="ml-2 text-gray-500">{{ tpl.description }}</span>
            <span class="mt-1 block font-mono text-xs text-gray-500">
              {{ tpl.nodes.map((n) => n.task_type).join(' → ') }}
            </span>
          </li>
        </ul>
      </section>

      <section>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">
          {{ $t('pipelines.instances') }}
        </h2>
        <div
          v-if="!data.instances.length"
          class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
        >
          {{ $t('pipelines.empty') }}
        </div>
        <div v-else class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-900">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $t('pipelines.columns.id') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $t('pipelines.columns.template') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $t('pipelines.columns.status') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $t('pipelines.columns.nodes') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $t('pipelines.columns.created') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-950">
              <tr v-for="instance in data.instances" :key="instance.id">
                <td class="px-4 py-3 font-mono text-xs">
                  <RouterLink
                    :to="`/pipelines/${instance.template_name}/instances/${instance.id}`"
                    class="text-violet-600 hover:underline"
                    data-testid="pipeline-instance-link"
                  >
                    {{ instance.id }}
                  </RouterLink>
                </td>
                <td class="px-4 py-3 text-sm">{{ instance.template_name }}</td>
                <td class="px-4 py-3 text-sm">{{ statusLabel(instance.status) }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ instance.nodes?.length ?? 0 }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(instance.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>
