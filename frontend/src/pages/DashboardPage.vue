<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { DashboardStats } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const STALE_MS = 2 * 60 * 1000

const { t, locale } = useI18n()
const tenant = useTenantStore()

const emptyStats = (): DashboardStats => ({
  active_tasks: 0,
  paused_tasks: 0,
  recent_runs: 0,
  failed_runs_24h: 0,
  retry_wait_runs: 0,
  dead_runs: 0,
  upcoming_tasks: [],
  recent_run_items: [],
  oldest_due_at: null,
  scheduler_last_seen_at: null,
})

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    return emptyStats()
  }

  const path = tenant.tenantPath('/dashboard')
  const { data: response } = await api.get<{ data: DashboardStats }>(path)
  return {
    ...emptyStats(),
    ...response.data,
    upcoming_tasks: response.data.upcoming_tasks ?? [],
    recent_run_items: response.data.recent_run_items ?? [],
  }
})

const needsTenant = computed(
  () => !tenant.currentTenantId || !tenant.currentEnvironmentId,
)

const schedulerStale = computed(() => {
  const seen = data.value?.scheduler_last_seen_at
  if (!seen) {
    return true
  }
  const ts = new Date(seen).getTime()
  if (Number.isNaN(ts)) {
    return true
  }
  return Date.now() - ts > STALE_MS
})

const stats = computed(() => [
  {
    label: t('dashboard.stats.activeTasks'),
    value: data.value?.active_tasks ?? 0,
    to: '/tasks',
  },
  {
    label: t('dashboard.stats.pausedTasks'),
    value: data.value?.paused_tasks ?? 0,
    to: '/tasks',
  },
  {
    label: t('dashboard.stats.recentRuns'),
    value: data.value?.recent_runs ?? 0,
    to: '/runs',
  },
  {
    label: t('dashboard.stats.failedRuns'),
    value: data.value?.failed_runs_24h ?? 0,
    to: '/runs?run_state=dead',
  },
  {
    label: t('dashboard.stats.retryWait'),
    value: data.value?.retry_wait_runs ?? 0,
    to: '/runs?run_state=retry_wait',
  },
  {
    label: t('dashboard.stats.deadRuns'),
    value: data.value?.dead_runs ?? 0,
    to: '/runs?run_state=dead',
  },
])

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
</script>

<template>
  <div>
    <PageHeader :title="$t('dashboard.title')" :subtitle="$t('dashboard.subtitle')">
      <template #actions>
        <RouterLink
          to="/tasks/new"
          class="rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
        >
          {{ $t('dashboard.createTask') }}
        </RouterLink>
      </template>
    </PageHeader>

    <LoadingState v-if="loading" />
    <ErrorState v-else-if="error" :message="error" @retry="reload" />
    <div
      v-else-if="needsTenant"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('dashboard.needsTenant') }}
    </div>
    <template v-else>
      <div
        v-if="schedulerStale"
        class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
        role="status"
      >
        <p class="font-medium">{{ $t('dashboard.scheduler.staleTitle') }}</p>
        <p class="mt-1">
          {{
            $t('dashboard.scheduler.staleBody', {
              lastSeen: formatDate(data?.scheduler_last_seen_at),
            })
          }}
        </p>
      </div>
      <div
        v-else
        class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900 dark:border-green-900 dark:bg-green-950 dark:text-green-100"
      >
        {{
          $t('dashboard.scheduler.ok', {
            lastSeen: formatDate(data?.scheduler_last_seen_at),
          })
        }}
      </div>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <RouterLink
          v-for="stat in stats"
          :key="stat.label"
          :to="stat.to"
          class="rounded-lg border border-gray-200 bg-white p-5 transition hover:border-violet-300 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-violet-700"
        >
          <p class="text-sm text-gray-500">{{ stat.label }}</p>
          <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-gray-100">
            {{ stat.value }}
          </p>
        </RouterLink>
      </div>

      <section class="mt-8">
        <div class="mb-3 flex items-center justify-between gap-3">
          <h2 class="text-lg font-medium">{{ $t('dashboard.recent.title') }}</h2>
          <RouterLink to="/runs" class="text-sm text-violet-600 hover:underline">
            {{ $t('dashboard.recent.viewAll') }}
          </RouterLink>
        </div>
        <div
          v-if="!data?.recent_run_items?.length"
          class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-gray-500"
        >
          {{ $t('dashboard.recent.empty') }}
        </div>
        <ul
          v-else
          class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800"
        >
          <li
            v-for="run in data.recent_run_items"
            :key="run.id"
            class="flex items-center justify-between gap-3 bg-white px-4 py-3 dark:bg-gray-900"
          >
            <div class="min-w-0">
              <RouterLink
                :to="`/runs/${run.id}`"
                class="text-sm font-medium text-violet-600 hover:underline"
              >
                {{ run.task_name || run.task_id || run.id }}
              </RouterLink>
              <p class="truncate text-xs text-gray-500">
                {{ $t(`runs.status.${run.run_state}`, run.run_state) }}
              </p>
            </div>
            <span class="shrink-0 text-sm text-gray-500">
              {{ formatDate(run.finished_at || run.created_at) }}
            </span>
          </li>
        </ul>
      </section>

      <section class="mt-8">
        <h2 class="mb-3 text-lg font-medium">{{ $t('dashboard.upcoming.title') }}</h2>
        <div
          v-if="!data?.upcoming_tasks?.length"
          class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-gray-500"
        >
          {{ $t('dashboard.upcoming.empty') }}
        </div>
        <ul
          v-else
          class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800"
        >
          <li
            v-for="task in data.upcoming_tasks"
            :key="task.id"
            class="flex items-center justify-between bg-white px-4 py-3 dark:bg-gray-900"
          >
            <RouterLink
              :to="`/tasks/${task.id}`"
              class="text-sm font-medium text-violet-600 hover:underline"
            >
              {{ task.name }}
            </RouterLink>
            <span class="text-sm text-gray-500">
              {{ formatDate(task.next_run_at) }}
            </span>
          </li>
        </ul>
      </section>

      <p
        v-if="data?.oldest_due_at"
        class="mt-4 text-sm text-gray-500"
      >
        {{
          $t('dashboard.oldestDue', {
            at: formatDate(data.oldest_due_at),
          })
        }}
      </p>

      <p
        v-if="stats.every((s) => s.value === 0)"
        class="mt-8 text-center text-gray-500"
      >
        {{ $t('dashboard.empty') }}
      </p>
    </template>
  </div>
</template>
