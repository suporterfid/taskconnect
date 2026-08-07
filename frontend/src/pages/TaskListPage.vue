<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { ScheduleKind, Task, TaskDefinitionStatus } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'
import { formatScheduleHuman } from '@/utils/scheduleHuman'
import { toneForRunState, toneForTaskStatus } from '@/utils/statusTone'

const { locale, t } = useI18n()
const route = useRoute()
const router = useRouter()
const tenant = useTenantStore()

const SCHEDULE_KINDS: ScheduleKind[] = [
  'once',
  'every_n_minutes',
  'hourly_at',
  'daily_at',
  'weekly_on',
  'monthly_on_day',
  'business_days_at',
  'cron',
]

function queryString(key: string): string {
  const raw = route.query[key]
  return typeof raw === 'string' ? raw : ''
}

const filters = reactive({
  q: queryString('q'),
  definition_status: queryString('definition_status') as '' | TaskDefinitionStatus,
  last_run_state: queryString('last_run_state'),
  schedule_kind: queryString('schedule_kind') as '' | ScheduleKind,
  sort: (queryString('sort') || 'name') as 'name' | 'next_run_at' | 'last_run_at',
  order: (queryString('order') || 'asc') as 'asc' | 'desc',
})

const selectedIds = ref<string[]>([])
const actionError = ref<string | null>(null)
const bulkBusy = ref(false)
const duplicatingId = ref<string | null>(null)

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    return [] as Task[]
  }
  const params: Record<string, string> = {
    sort: filters.sort,
    order: filters.order,
  }
  if (filters.q.trim()) {
    params.q = filters.q.trim()
  }
  if (filters.definition_status) {
    params.definition_status = filters.definition_status
  }
  if (filters.last_run_state) {
    params.last_run_state = filters.last_run_state
  }
  if (filters.schedule_kind) {
    params.schedule_kind = filters.schedule_kind
  }
  const { data: response } = await api.get<{ data: Task[] }>(
    tenant.tenantPath('/tasks'),
    { params },
  )
  return response.data ?? []
})

watch(
  () => route.query.last_run_state,
  (value) => {
    const next = typeof value === 'string' ? value : ''
    if (filters.last_run_state !== next) {
      filters.last_run_state = next
    }
  },
)

watch(filters, () => {
  selectedIds.value = []
  actionError.value = null
  void reload()
})

watch(data, () => {
  const visible = new Set((data.value ?? []).map((task) => task.id))
  selectedIds.value = selectedIds.value.filter((id) => visible.has(id))
})

const allSelected = computed(() => {
  const tasks = data.value ?? []
  return tasks.length > 0 && tasks.every((task) => selectedIds.value.includes(task.id))
})

const someSelected = computed(() => selectedIds.value.length > 0)

const hasActiveFilters = computed(
  () =>
    Boolean(
      filters.q.trim() ||
        filters.definition_status ||
        filters.last_run_state ||
        filters.schedule_kind,
    ),
)

function toggleSelectAll(): void {
  const tasks = data.value ?? []
  if (allSelected.value) {
    selectedIds.value = []
    return
  }
  selectedIds.value = tasks.map((task) => task.id)
}

function toggleRow(id: string): void {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter((item) => item !== id)
    return
  }
  selectedIds.value = [...selectedIds.value, id]
}

async function bulkAction(action: 'pause' | 'resume'): Promise<void> {
  if (!someSelected.value || bulkBusy.value) {
    return
  }

  bulkBusy.value = true
  actionError.value = null

  try {
    const path =
      action === 'pause' ? '/tasks/bulk-pause' : '/tasks/bulk-resume'
    await api.post(tenant.tenantPath(path), {
      task_ids: selectedIds.value,
    })
    selectedIds.value = []
    await reload()
  } catch (err) {
    actionError.value =
      err instanceof ApiError ? err.message : t('tasks.bulk.error')
  } finally {
    bulkBusy.value = false
  }
}

async function onDuplicate(task: Task): Promise<void> {
  if (duplicatingId.value) {
    return
  }
  duplicatingId.value = task.id
  actionError.value = null
  try {
    const { data: response } = await api.post<{ data: Task }>(
      tenant.tenantPath(`/tasks/${task.id}/duplicate`),
    )
    if (response.data?.id) {
      await router.push(`/tasks/${response.data.id}`)
    } else {
      await reload()
    }
  } catch (err) {
    actionError.value =
      err instanceof ApiError ? err.message : t('tasks.actions.error')
  } finally {
    duplicatingId.value = null
  }
}

function toggleSort(column: 'name' | 'next_run_at' | 'last_run_at'): void {
  if (filters.sort === column) {
    filters.order = filters.order === 'asc' ? 'desc' : 'asc'
    return
  }
  filters.sort = column
  filters.order = column === 'name' ? 'asc' : 'desc'
}

function sortIndicator(column: string): string {
  if (filters.sort !== column) {
    return ''
  }
  return filters.order === 'asc' ? ' ↑' : ' ↓'
}

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

function scheduleLabel(task: Task): string {
  return formatScheduleHuman(task.schedule_human, t) || '—'
}

</script>

<template>
  <div>
    <div class="page-header">
      <PageHeader :title="$t('tasks.title')" :subtitle="$t('tasks.subtitle')" />
      <RouterLink to="/tasks/new" class="action-link">
        {{ $t('tasks.create') }}
      </RouterLink>
    </div>

    <div
      v-if="tenant.currentTenantId && tenant.currentEnvironmentId"
      class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5"
    >
      <label class="block text-sm">
        <span class="font-medium text-text">{{ $t('common.search') }}</span>
        <BaseInput v-model="filters.q" type="search" class="mt-1" :placeholder="$t('tasks.filters.searchPlaceholder')" />
      </label>
      <label class="block text-sm">
        <span class="font-medium text-text">{{ $t('common.status') }}</span>
        <BaseSelect v-model="filters.definition_status" class="mt-1">
          <option value="">{{ $t('tasks.filters.anyStatus') }}</option>
          <option value="draft">{{ $t('tasks.status.draft') }}</option>
          <option value="active">{{ $t('tasks.status.active') }}</option>
          <option value="paused">{{ $t('tasks.status.paused') }}</option>
          <option value="completed">{{ $t('tasks.status.completed') }}</option>
        </BaseSelect>
      </label>
      <label class="block text-sm">
        <span class="font-medium text-text">{{ $t('tasks.detail.lastRunState') }}</span>
        <BaseSelect v-model="filters.last_run_state" class="mt-1">
          <option value="">{{ $t('tasks.filters.anyRunState') }}</option>
          <option value="succeeded">{{ $t('runs.status.succeeded') }}</option>
          <option value="dead">{{ $t('runs.status.dead') }}</option>
          <option value="retry_wait">{{ $t('runs.status.retry_wait') }}</option>
          <option value="pending">{{ $t('runs.status.pending') }}</option>
          <option value="blocked">{{ $t('runs.status.blocked') }}</option>
        </BaseSelect>
      </label>
      <label class="block text-sm">
        <span class="font-medium text-text">{{ $t('tasks.filters.scheduleKind') }}</span>
        <BaseSelect v-model="filters.schedule_kind" class="mt-1">
          <option value="">{{ $t('tasks.filters.anyScheduleKind') }}</option>
          <option v-for="kind in SCHEDULE_KINDS" :key="kind" :value="kind">
            {{ $t(`tasks.scheduleKinds.${kind}`) }}
          </option>
        </BaseSelect>
      </label>
      <p class="self-end text-sm tabular-nums text-muted">
        {{ t('tasks.filters.resultCount', { count: data?.length ?? 0 }) }}
      </p>
    </div>

    <LoadingState v-if="loading" />
    <ErrorState v-else-if="error" :message="error ?? $t('tasks.loadError')" @retry="reload" />
    <EmptyState v-else-if="!tenant.currentTenantId || !tenant.currentEnvironmentId" :message="$t('tasks.needsTenant')" />
    <EmptyState
      v-else-if="!data?.length && hasActiveFilters"
      :message="$t('tasks.filters.resultCount', { count: 0 })"
    />
    <EmptyState v-else-if="!data?.length" :message="$t('tasks.empty')">
      <p>{{ $t('tasks.empty') }}</p>
      <RouterLink
        to="/tasks/new"
        class="action-link mt-4"
      >
        {{ $t('tasks.emptyCta') }}
      </RouterLink>
    </EmptyState>
    <div v-else>
      <div
        v-if="someSelected"
        class="mb-3 flex flex-wrap items-center gap-3 rounded-md border border-border-strong bg-surface-emphasis px-4 py-3"
      >
        <span class="text-sm font-medium text-text">
          {{ $t('tasks.bulk.selected', { count: selectedIds.length }) }}
        </span>
        <BaseButton size="sm" :disabled="bulkBusy" @click="bulkAction('pause')">
          {{ $t('tasks.bulk.pause') }}
        </BaseButton>
        <BaseButton variant="secondary" size="sm" :disabled="bulkBusy" @click="bulkAction('resume')">
          {{ $t('tasks.bulk.resume') }}
        </BaseButton>
      </div>

      <BaseAlert v-if="actionError" tone="danger" class="mb-3">
        {{ actionError }}
      </BaseAlert>

      <div class="table-scroll" role="region" tabindex="0" :aria-label="$t('common.table.scrollRegion')">
        <table class="min-w-full divide-y divide-border">
          <thead class="bg-surface">
            <tr>
              <th class="w-10 px-4 py-3 text-start">
                <input
                  type="checkbox"
                  class="rounded"
                  :checked="allSelected"
                  :aria-label="$t('tasks.bulk.selectAll')"
                  @change="toggleSelectAll"
                />
              </th>
              <th class="px-4 py-3 text-start text-sm font-medium text-muted">
                <button type="button" class="link" @click="toggleSort('name')">
                  {{ $t('common.name') }}{{ sortIndicator('name') }}
                </button>
              </th>
              <th class="px-4 py-3 text-start text-sm font-medium text-muted">
                {{ $t('common.status') }}
              </th>
              <th class="px-4 py-3 text-start text-sm font-medium text-muted">
                {{ $t('tasks.fields.taskType') }}
              </th>
              <th class="px-4 py-3 text-start text-sm font-medium text-muted">
                {{ $t('tasks.fields.priority') }}
              </th>
              <th class="px-4 py-3 text-start text-sm font-medium text-muted">
                {{ $t('tasks.fields.egressProfile') }}
              </th>
              <th class="px-4 py-3 text-start text-sm font-medium text-muted">
                {{ $t('tasks.detail.schedule') }}
              </th>
              <th class="px-4 py-3 text-start text-sm font-medium text-muted">
                <button type="button" class="link" @click="toggleSort('next_run_at')">
                  {{ $t('tasks.detail.nextRun') }}{{ sortIndicator('next_run_at') }}
                </button>
              </th>
              <th class="px-4 py-3 text-start text-sm font-medium text-muted">
                <button type="button" class="link" @click="toggleSort('last_run_at')">
                  {{ $t('tasks.detail.lastRunState') }}{{ sortIndicator('last_run_at') }}
                </button>
              </th>
              <th class="px-4 py-3 text-end text-sm font-medium text-muted">
                {{ $t('common.actions') }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border bg-surface">
            <tr v-for="task in data" :key="task.id">
              <td class="px-4 py-3">
                <input
                  type="checkbox"
                  class="rounded"
                  :checked="selectedIds.includes(task.id)"
                  :aria-label="$t('tasks.bulk.selectRow', { name: task.name })"
                  @change="toggleRow(task.id)"
                />
              </td>
              <td class="px-4 py-3 text-sm font-medium">
                <RouterLink :to="`/tasks/${task.id}`" class="link text-action-text">
                  {{ task.name }}
                </RouterLink>
              </td>
              <td class="px-4 py-3 text-sm">
                <BaseBadge
                  :label="$t(`tasks.status.${task.definition_status}`)"
                  :tone="toneForTaskStatus(task.definition_status).tone"
                  :icon="toneForTaskStatus(task.definition_status).icon"
                />
              </td>
              <td class="px-4 py-3 font-mono text-xs text-muted">
                {{ task.task_type || '—' }}
              </td>
              <td class="px-4 py-3 text-sm tabular-nums text-muted">
                {{ task.priority ?? '—' }}
              </td>
              <td class="px-4 py-3 font-mono text-xs text-muted">
                {{ task.egress_profile || '—' }}
              </td>
              <td class="max-w-xs truncate px-4 py-3 text-sm text-muted">
                {{ scheduleLabel(task) }}
              </td>
              <td class="px-4 py-3 text-sm tabular-nums text-muted">
                {{ formatDate(task.next_run_at) }}
              </td>
              <td class="px-4 py-3 text-sm">
                <BaseBadge
                  v-if="task.last_run_state"
                  :label="$t(`runs.status.${task.last_run_state}`, task.last_run_state)"
                  :tone="toneForRunState(task.last_run_state).tone"
                  :icon="toneForRunState(task.last_run_state).icon"
                />
                <span v-else class="text-sm text-muted">—</span>
              </td>
              <td class="[&>*+*]:ms-3 px-4 py-3 text-end text-sm">
                <RouterLink :to="`/tasks/${task.id}`" class="link text-action-text">
                  {{ $t('tasks.view') }}
                </RouterLink>
                <button
                  type="button"
                  class="link text-action-text disabled:opacity-60"
                  :disabled="duplicatingId === task.id"
                  @click="onDuplicate(task)"
                >
                  {{ $t('tasks.actions.duplicate') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
