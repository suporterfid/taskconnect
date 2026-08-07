<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
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
  <div data-testid="dlq-page">
    <PageHeader :title="$t('dlq.title')" :subtitle="$t('dlq.subtitle')" class="mb-8" />

    <div
      v-if="tenant.currentTenantId && tenant.currentEnvironmentId"
      class="mb-4 flex flex-wrap items-end gap-3"
    >
      <label class="block text-sm">
        <span class="font-medium text-text">{{ $t('dlq.filters.type') }}</span>
        <BaseInput
          v-model="typeFilter"
          type="search"
          class="mt-1 w-64"
          :placeholder="$t('dlq.filters.anyType')"
          @change="onFilter"
        />
      </label>
      <BaseButton variant="secondary" @click="onFilter">
        {{ $t('common.search') }}
      </BaseButton>
    </div>

    <LoadingState v-if="loading" />
    <ErrorState v-else-if="error" :message="error" @retry="reload" />
    <EmptyState v-else-if="!tenant.currentTenantId || !tenant.currentEnvironmentId" :message="$t('dlq.needsTenant')" />
    <EmptyState v-else-if="!data?.length" data-testid="dlq-empty" :message="$t('dlq.empty')" />
    <div v-else data-testid="dlq-table">
      <BaseAlert v-if="actionError" tone="danger" class="mb-3">{{ actionError }}</BaseAlert>
      <div class="table-scroll" role="region" tabindex="0" :aria-label="$t('common.table.scrollRegion')">
        <table class="min-w-full divide-y divide-border">
          <thead class="bg-surface">
            <tr>
              <th class="px-4 py-3 text-start text-sm font-medium text-muted">{{ $t('dlq.columns.run') }}</th>
              <th class="px-4 py-3 text-start text-sm font-medium text-muted">{{ $t('dlq.columns.task') }}</th>
              <th class="px-4 py-3 text-start text-sm font-medium text-muted">{{ $t('dlq.columns.type') }}</th>
              <th class="px-4 py-3 text-start text-sm font-medium text-muted">{{ $t('dlq.columns.finished') }}</th>
              <th class="px-4 py-3 text-start text-sm font-medium text-muted">{{ $t('dlq.columns.error') }}</th>
              <th class="px-4 py-3 text-end text-sm font-medium text-muted">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border bg-surface">
            <tr v-for="run in data" :key="run.id" :data-testid="`dlq-row-${run.id}`">
              <td class="px-4 py-3 font-mono text-xs text-text">
                <RouterLink
                  :to="`/runs/${run.id}`"
                  class="link text-action-text"
                  data-testid="dlq-run-link"
                >
                  {{ run.id }}
                </RouterLink>
              </td>
              <td class="px-4 py-3 text-sm">
                <RouterLink v-if="run.task_id" :to="`/tasks/${run.task_id}`" class="link text-action-text">
                  {{ run.task_name || run.task_id }}
                </RouterLink>
                <span v-else class="text-muted">—</span>
              </td>
              <td class="px-4 py-3 font-mono text-xs text-muted">{{ run.task_type || '—' }}</td>
              <td class="px-4 py-3 text-sm tabular-nums text-muted">{{ formatDate(run.finished_at) }}</td>
              <td class="px-4 py-3 font-mono text-xs text-muted">
                {{ run.final_error_code || run.final_http_status || '—' }}
              </td>
              <td class="[&>*+*]:ms-3 px-4 py-3 text-end text-sm">
                <RouterLink :to="`/runs/${run.id}`" class="link text-action-text" data-testid="dlq-inspect">
                  {{ $t('dlq.actions.inspect') }}
                </RouterLink>
                <button
                  type="button"
                  class="link text-action-text disabled:opacity-60"
                  data-testid="dlq-replay"
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
