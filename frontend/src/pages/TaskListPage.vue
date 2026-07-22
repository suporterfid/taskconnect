<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { ScheduleKind, Task, TaskDefinitionStatus } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'
import { formatScheduleHuman } from '@/utils/scheduleHuman'

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

function statusClass(status: string): string {
  if (status === 'active') {
    return 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300'
  }
  if (status === 'paused') {
    return 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200'
  }
  if (status === 'archived' || status === 'completed') {
    return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'
  }
  return 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300'
}
</script>

<template>
  <div>
    <div class="mb-8 flex items-start justify-between gap-4">
      <PageHeader :title="$t('tasks.title')" :subtitle="$t('tasks.subtitle')" />
      <RouterLink
        to="/tasks/new"
        class="rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
      >
        {{ $t('tasks.create') }}
      </RouterLink>
    </div>

    <div
      v-if="tenant.currentTenantId && tenant.currentEnvironmentId"
      class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5"
    >
      <label class="block text-sm">
        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $t('common.search') }}</span>
        <input
          v-model="filters.q"
          type="search"
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
          :placeholder="$t('tasks.filters.searchPlaceholder')"
        />
      </label>
      <label class="block text-sm">
        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $t('common.status') }}</span>
        <select
          v-model="filters.definition_status"
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
        >
          <option value="">{{ $t('tasks.filters.anyStatus') }}</option>
          <option value="draft">{{ $t('tasks.status.draft') }}</option>
          <option value="active">{{ $t('tasks.status.active') }}</option>
          <option value="paused">{{ $t('tasks.status.paused') }}</option>
          <option value="completed">{{ $t('tasks.status.completed') }}</option>
        </select>
      </label>
      <label class="block text-sm">
        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $t('tasks.detail.lastRunState') }}</span>
        <select
          v-model="filters.last_run_state"
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
        >
          <option value="">{{ $t('tasks.filters.anyRunState') }}</option>
          <option value="succeeded">{{ $t('runs.status.succeeded') }}</option>
          <option value="dead">{{ $t('runs.status.dead') }}</option>
          <option value="retry_wait">{{ $t('runs.status.retry_wait') }}</option>
          <option value="pending">{{ $t('runs.status.pending') }}</option>
          <option value="blocked">{{ $t('runs.status.blocked') }}</option>
        </select>
      </label>
      <label class="block text-sm">
        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $t('tasks.filters.scheduleKind') }}</span>
        <select
          v-model="filters.schedule_kind"
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
        >
          <option value="">{{ $t('tasks.filters.anyScheduleKind') }}</option>
          <option
            v-for="kind in SCHEDULE_KINDS"
            :key="kind"
            :value="kind"
          >
            {{ $t(`tasks.scheduleKinds.${kind}`) }}
          </option>
        </select>
      </label>
      <p class="self-end text-sm text-gray-500">
        {{ t('tasks.filters.resultCount', { count: data?.length ?? 0 }) }}
      </p>
    </div>

    <LoadingState v-if="loading" />
    <ErrorState v-else-if="error" :message="error ?? $t('tasks.loadError')" @retry="reload" />
    <div
      v-else-if="!tenant.currentTenantId || !tenant.currentEnvironmentId"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('tasks.needsTenant') }}
    </div>
    <div
      v-else-if="!data?.length"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      <p>{{ hasActiveFilters ? $t('tasks.filters.resultCount', { count: 0 }) : $t('tasks.empty') }}</p>
      <RouterLink
        v-if="!hasActiveFilters"
        to="/tasks/new"
        class="mt-4 inline-block rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
      >
        {{ $t('tasks.emptyCta') }}
      </RouterLink>
    </div>
    <div v-else>
      <div
        v-if="someSelected"
        class="mb-3 flex flex-wrap items-center gap-3 rounded-md border border-violet-200 bg-violet-50 px-4 py-3 dark:border-violet-900 dark:bg-violet-950/40"
      >
        <span class="text-sm font-medium text-violet-900 dark:text-violet-100">
          {{ $t('tasks.bulk.selected', { count: selectedIds.length }) }}
        </span>
        <button
          type="button"
          class="rounded-md bg-violet-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50"
          :disabled="bulkBusy"
          @click="bulkAction('pause')"
        >
          {{ $t('tasks.bulk.pause') }}
        </button>
        <button
          type="button"
          class="rounded-md border border-violet-300 bg-white px-3 py-1.5 text-sm font-medium text-violet-700 hover:bg-violet-50 disabled:opacity-50 dark:border-violet-800 dark:bg-gray-950 dark:text-violet-200 dark:hover:bg-violet-950"
          :disabled="bulkBusy"
          @click="bulkAction('resume')"
        >
          {{ $t('tasks.bulk.resume') }}
        </button>
      </div>

      <p v-if="actionError" class="mb-3 text-sm text-red-600" role="alert">
        {{ actionError }}
      </p>

      <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="w-10 px-4 py-3 text-left">
                <input
                  type="checkbox"
                  class="rounded"
                  :checked="allSelected"
                  :aria-label="$t('tasks.bulk.selectAll')"
                  @change="toggleSelectAll"
                />
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                <button type="button" class="hover:underline" @click="toggleSort('name')">
                  {{ $t('common.name') }}{{ sortIndicator('name') }}
                </button>
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                {{ $t('common.status') }}
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                {{ $t('tasks.fields.taskType') }}
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                {{ $t('tasks.fields.priority') }}
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                {{ $t('tasks.fields.egressProfile') }}
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                {{ $t('tasks.detail.schedule') }}
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                <button type="button" class="hover:underline" @click="toggleSort('next_run_at')">
                  {{ $t('tasks.detail.nextRun') }}{{ sortIndicator('next_run_at') }}
                </button>
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                <button type="button" class="hover:underline" @click="toggleSort('last_run_at')">
                  {{ $t('tasks.detail.lastRunState') }}{{ sortIndicator('last_run_at') }}
                </button>
              </th>
              <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">
                {{ $t('common.actions') }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-950">
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
                <RouterLink
                  :to="`/tasks/${task.id}`"
                  class="text-violet-600 hover:underline"
                >
                  {{ task.name }}
                </RouterLink>
              </td>
              <td class="px-4 py-3 text-sm">
                <span
                  class="rounded px-2 py-0.5 text-xs font-medium"
                  :class="statusClass(task.definition_status)"
                >
                  {{ $t(`tasks.status.${task.definition_status}`) }}
                </span>
              </td>
              <td class="px-4 py-3 font-mono text-xs text-gray-600">
                {{ task.task_type || '—' }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">
                {{ task.priority ?? '—' }}
              </td>
              <td class="px-4 py-3 font-mono text-xs text-gray-600">
                {{ task.egress_profile || '—' }}
              </td>
              <td class="max-w-xs truncate px-4 py-3 text-sm text-gray-600">
                {{ scheduleLabel(task) }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">
                {{ formatDate(task.next_run_at) }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">
                <template v-if="task.last_run_state">
                  {{ $t(`runs.status.${task.last_run_state}`, task.last_run_state) }}
                </template>
                <template v-else>—</template>
              </td>
              <td class="space-x-3 px-4 py-3 text-right text-sm">
                <RouterLink
                  :to="`/tasks/${task.id}`"
                  class="text-violet-600 hover:underline"
                >
                  {{ $t('tasks.view') }}
                </RouterLink>
                <button
                  type="button"
                  class="text-violet-600 hover:underline disabled:opacity-60"
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
