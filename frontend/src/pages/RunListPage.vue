<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { RunState, TaskRun } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'
import { toneForRunState } from '@/utils/statusTone'

const { t, locale } = useI18n()
const route = useRoute()
const tenant = useTenantStore()

const actionError = ref<string | null>(null)
const actionLoading = ref<string | null>(null)

const taskIdFilter = computed(() => {
  const raw = route.query.task_id
  return typeof raw === 'string' && raw.length > 0 ? raw : null
})

const runStateFilter = computed(() => {
  const raw = route.query.run_state
  return typeof raw === 'string' && raw.length > 0 ? raw : null
})

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    return [] as TaskRun[]
  }
  const params: Record<string, string> = {}
  if (taskIdFilter.value) {
    params.task_id = taskIdFilter.value
  }
  if (runStateFilter.value) {
    params.run_state = runStateFilter.value
  }
  const { data: response } = await api.get<{ data: TaskRun[] }>(
    tenant.tenantPath('/task-runs'),
    { params },
  )
  return response.data ?? []
})

watch([taskIdFilter, runStateFilter], () => {
  void reload()
})

const filtered = computed(() => data.value ?? [])

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

function displayTime(run: TaskRun): string {
  return formatDate(run.started_at || run.created_at)
}

function canCancel(state: RunState | string): boolean {
  return state === 'pending' || state === 'running' || state === 'retry_wait'
}

function canRetry(state: RunState | string): boolean {
  return state === 'dead' || state === 'retry_wait'
}

async function onCancel(run: TaskRun): Promise<void> {
  if (!confirm(t('runs.list.cancelConfirm'))) {
    return
  }
  actionLoading.value = `cancel:${run.id}`
  actionError.value = null
  try {
    await api.post(tenant.tenantPath(`/task-runs/${run.id}/cancel`))
    await reload()
  } catch (err) {
    actionError.value =
      err instanceof ApiError ? err.message : t('runs.actions.error')
  } finally {
    actionLoading.value = null
  }
}

async function onRetry(run: TaskRun): Promise<void> {
  if (!confirm(t('runs.list.retryConfirm'))) {
    return
  }
  actionLoading.value = `retry:${run.id}`
  actionError.value = null
  try {
    await api.post(tenant.tenantPath(`/task-runs/${run.id}/retry`))
    await reload()
  } catch (err) {
    actionError.value =
      err instanceof ApiError ? err.message : t('runs.actions.error')
  } finally {
    actionLoading.value = null
  }
}
</script>

<template>
  <div>
    <PageHeader :title="$t('runs.title')" :subtitle="$t('runs.subtitle')" />

    <p v-if="taskIdFilter || runStateFilter" class="mb-4 text-sm text-muted">
      <template v-if="taskIdFilter">
        {{ $t('runs.filteredByTask') }}
        <RouterLink :to="`/tasks/${taskIdFilter}`" class="link text-action-text">
          {{ taskIdFilter }}
        </RouterLink>
      </template>
      <template v-if="runStateFilter">
        <span v-if="taskIdFilter"> · </span>
        {{ $t('runs.filteredByState') }}
        <span class="font-medium text-text">
          {{ $t(`runs.status.${runStateFilter}`, runStateFilter) }}
        </span>
      </template>
      ·
      <RouterLink to="/runs" class="link text-action-text">
        {{ $t('runs.clearFilter') }}
      </RouterLink>
    </p>

    <BaseAlert v-if="actionError" tone="danger" class="mb-4">{{ actionError }}</BaseAlert>

    <LoadingState v-if="loading" />
    <ErrorState v-else-if="error" :message="error ?? $t('runs.loadError')" @retry="reload" />
    <EmptyState v-else-if="!tenant.currentTenantId || !tenant.currentEnvironmentId" :message="$t('runs.needsTenant')" />
    <EmptyState v-else-if="!filtered.length" :message="$t('runs.empty')" />
    <div v-else class="overflow-x-auto rounded-lg border border-border">
      <table class="min-w-full divide-y divide-border">
        <thead class="bg-surface">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('runs.fields.id') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('runs.fields.task') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('common.status') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('runs.fields.when') }}
            </th>
            <th class="px-4 py-3 text-right text-sm font-medium text-muted">
              {{ $t('common.actions') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border bg-surface">
          <tr v-for="run in filtered" :key="run.id">
            <td class="px-4 py-3 font-mono text-sm text-text">{{ run.id }}</td>
            <td class="px-4 py-3 text-sm">
              <RouterLink v-if="run.task_id" :to="`/tasks/${run.task_id}`" class="link text-action-text">
                {{ run.task_id }}
              </RouterLink>
              <span v-else class="text-muted">—</span>
            </td>
            <td class="px-4 py-3 text-sm">
              <BaseBadge
                :label="$t(`runs.status.${run.run_state}`, run.run_state)"
                :tone="toneForRunState(run.run_state).tone"
                :icon="toneForRunState(run.run_state).icon"
              />
            </td>
            <td class="px-4 py-3 text-sm tabular-nums text-muted">
              {{ displayTime(run) }}
            </td>
            <td class="px-4 py-3 text-right text-sm">
              <div class="flex flex-wrap items-center justify-end gap-3">
                <button
                  v-if="canCancel(run.run_state)"
                  type="button"
                  class="link text-muted disabled:opacity-60"
                  :disabled="actionLoading !== null"
                  @click="onCancel(run)"
                >
                  {{ $t('runs.actions.cancel') }}
                </button>
                <button
                  v-if="canRetry(run.run_state)"
                  type="button"
                  class="link text-action-text disabled:opacity-60"
                  :disabled="actionLoading !== null"
                  @click="onRetry(run)"
                >
                  {{ $t('runs.actions.retry') }}
                </button>
                <RouterLink :to="`/runs/${run.id}`" class="link text-action-text">
                  {{ $t('runs.view') }}
                </RouterLink>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
