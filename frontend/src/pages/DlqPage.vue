<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { TaskRun } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const { t, locale } = useI18n()
const tenant = useTenantStore()

const actionError = ref<string | null>(null)
const actionLoading = ref<string | null>(null)
const typeFilter = ref('')

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    return [] as TaskRun[]
  }
  const params: Record<string, string> = { limit: '50' }
  if (typeFilter.value.trim()) {
    params.type = typeFilter.value.trim()
  }
  const { data: response } = await api.get<{ data: TaskRun[] }>(
    tenant.tenantPath('/dlq'),
    { params },
  )
  return response.data ?? []
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

async function onReplay(run: TaskRun): Promise<void> {
  if (!confirm(t('dlq.actions.replayConfirm'))) {
    return
  }
  actionLoading.value = run.id
  actionError.value = null
  try {
    await api.post(tenant.tenantPath(`/dlq/${run.id}/replay`))
    await reload()
  } catch (err) {
    actionError.value = err instanceof ApiError ? err.message : t('dlq.actions.error')
  } finally {
    actionLoading.value = null
  }
}

function onFilter(): void {
  void reload()
}
</script>

<template>
  <div>
    <PageHeader :title="$t('dlq.title')" :subtitle="$t('dlq.subtitle')" class="mb-8" />

    <div
      v-if="tenant.currentTenantId && tenant.currentEnvironmentId"
      class="mb-4 flex flex-wrap items-end gap-3"
    >
      <label class="block text-sm">
        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $t('dlq.filters.type') }}</span>
        <input
          v-model="typeFilter"
          type="search"
          class="mt-1 w-64 rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
          :placeholder="$t('dlq.filters.anyType')"
          @change="onFilter"
        />
      </label>
      <button
        type="button"
        class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 dark:border-gray-700"
        @click="onFilter"
      >
        {{ $t('common.search') }}
      </button>
    </div>

    <LoadingState v-if="loading" />
    <ErrorState v-else-if="error" :message="error" @retry="reload" />
    <div
      v-else-if="!tenant.currentTenantId || !tenant.currentEnvironmentId"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('dlq.needsTenant') }}
    </div>
    <div
      v-else-if="!data?.length"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('dlq.empty') }}
    </div>
    <div v-else>
      <p v-if="actionError" class="mb-3 text-sm text-red-600" role="alert">{{ actionError }}</p>
      <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $t('dlq.columns.run') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $t('dlq.columns.task') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $t('dlq.columns.type') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $t('dlq.columns.finished') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $t('dlq.columns.error') }}</th>
              <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-950">
            <tr v-for="run in data" :key="run.id">
              <td class="px-4 py-3 font-mono text-xs">
                <RouterLink :to="`/runs/${run.id}`" class="text-violet-600 hover:underline">
                  {{ run.id }}
                </RouterLink>
              </td>
              <td class="px-4 py-3 text-sm">
                <RouterLink
                  v-if="run.task_id"
                  :to="`/tasks/${run.task_id}`"
                  class="text-violet-600 hover:underline"
                >
                  {{ run.task_name || run.task_id }}
                </RouterLink>
                <span v-else>—</span>
              </td>
              <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ run.task_type || '—' }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(run.finished_at) }}</td>
              <td class="px-4 py-3 font-mono text-xs text-gray-600">
                {{ run.final_error_code || run.final_http_status || '—' }}
              </td>
              <td class="space-x-3 px-4 py-3 text-right text-sm">
                <RouterLink :to="`/runs/${run.id}`" class="text-violet-600 hover:underline">
                  {{ $t('dlq.actions.inspect') }}
                </RouterLink>
                <button
                  type="button"
                  class="text-violet-600 hover:underline disabled:opacity-60"
                  :disabled="actionLoading === run.id"
                  @click="onReplay(run)"
                >
                  {{ $t('dlq.actions.replay') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
