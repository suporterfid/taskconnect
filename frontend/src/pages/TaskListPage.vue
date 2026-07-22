<script setup lang="ts">
import { reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { Task, TaskDefinitionStatus } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const { locale, t } = useI18n()
const tenant = useTenantStore()

const filters = reactive({
  q: '',
  definition_status: '' as '' | TaskDefinitionStatus,
  last_run_state: '',
  sort: 'name' as 'name' | 'next_run_at' | 'last_run_at',
  order: 'asc' as 'asc' | 'desc',
})

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
  const { data: response } = await api.get<{ data: Task[] }>(
    tenant.tenantPath('/tasks'),
    { params },
  )
  return response.data ?? []
})

watch(filters, () => {
  void reload()
})

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
      class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
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
      {{ $t('tasks.empty') }}
    </div>
    <div v-else class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
        <thead class="bg-gray-50 dark:bg-gray-900">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              <button type="button" class="hover:underline" @click="toggleSort('name')">
                {{ $t('common.name') }}{{ sortIndicator('name') }}
              </button>
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('common.status') }}
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
            <td class="px-4 py-3 text-sm text-gray-600">
              {{ formatDate(task.next_run_at) }}
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">
              <template v-if="task.last_run_state">
                {{ $t(`runs.status.${task.last_run_state}`, task.last_run_state) }}
              </template>
              <template v-else>—</template>
            </td>
            <td class="px-4 py-3 text-right text-sm">
              <RouterLink
                :to="`/tasks/${task.id}`"
                class="text-violet-600 hover:underline"
              >
                {{ $t('tasks.view') }}
              </RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
