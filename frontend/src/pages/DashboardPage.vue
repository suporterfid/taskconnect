<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { DashboardStats } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'
import { toneForRunState } from '@/utils/statusTone'

const STALE_MS = 2 * 60 * 1000

const { t, locale } = useI18n()
const tenant = useTenantStore()

const emptyStats = (): DashboardStats => ({
  active_tasks: 0,
  paused_tasks: 0,
  recent_runs: 0,
  failed_runs_24h: 0,
  failed_tasks: 0,
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
    label: t('dashboard.stats.failedTasks'),
    value: data.value?.failed_tasks ?? 0,
    to: '/tasks?last_run_state=dead',
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
          class="rounded-md bg-action px-4 py-2 text-sm font-medium text-white hover:bg-action-hover"
        >
          {{ $t('dashboard.createTask') }}
        </RouterLink>
      </template>
    </PageHeader>

    <LoadingState v-if="loading" />
    <ErrorState v-else-if="error" :message="error" @retry="reload" />
    <EmptyState v-else-if="needsTenant" :message="$t('dashboard.needsTenant')" />
    <template v-else>
      <BaseAlert v-if="schedulerStale" tone="warning" role="status" class="mb-6">
        <p class="font-medium text-text">{{ $t('dashboard.scheduler.staleTitle') }}</p>
        <p class="mt-1">
          {{
            $t('dashboard.scheduler.staleBody', {
              lastSeen: formatDate(data?.scheduler_last_seen_at),
            })
          }}
        </p>
      </BaseAlert>
      <BaseAlert v-else tone="success" role="status" class="mb-6">
        {{
          $t('dashboard.scheduler.ok', {
            lastSeen: formatDate(data?.scheduler_last_seen_at),
          })
        }}
      </BaseAlert>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <RouterLink
          v-for="stat in stats"
          :key="stat.label"
          :to="stat.to"
          class="rounded-lg border border-border bg-surface p-5 transition-colors duration-standard ease-standard hover:border-border-strong"
        >
          <p class="text-sm text-muted">{{ stat.label }}</p>
          <p class="mt-2 text-3xl font-semibold tabular-nums text-text">
            {{ stat.value }}
          </p>
        </RouterLink>
      </div>

      <section class="mt-8">
        <div class="mb-3 flex items-center justify-between gap-3">
          <h2>{{ $t('dashboard.recent.title') }}</h2>
          <RouterLink to="/runs" class="link text-sm text-action">
            {{ $t('dashboard.recent.viewAll') }}
          </RouterLink>
        </div>
        <EmptyState v-if="!data?.recent_run_items?.length" :message="$t('dashboard.recent.empty')" />
        <ul v-else class="divide-y divide-border rounded-lg border border-border">
          <li
            v-for="run in data.recent_run_items"
            :key="run.id"
            class="flex items-center justify-between gap-3 bg-surface px-4 py-3"
          >
            <div class="min-w-0">
              <RouterLink :to="`/runs/${run.id}`" class="link text-sm font-medium text-action">
                {{ run.task_name || run.task_id || run.id }}
              </RouterLink>
              <div class="mt-0.5">
                <BaseBadge
                  :label="$t(`runs.status.${run.run_state}`, run.run_state)"
                  :tone="toneForRunState(run.run_state).tone"
                  :icon="toneForRunState(run.run_state).icon"
                />
              </div>
            </div>
            <span class="shrink-0 text-sm tabular-nums text-muted">
              {{ formatDate(run.finished_at || run.created_at) }}
            </span>
          </li>
        </ul>
      </section>

      <section class="mt-8">
        <h2 class="mb-3">{{ $t('dashboard.upcoming.title') }}</h2>
        <EmptyState v-if="!data?.upcoming_tasks?.length" :message="$t('dashboard.upcoming.empty')" />
        <ul v-else class="divide-y divide-border rounded-lg border border-border">
          <li
            v-for="task in data.upcoming_tasks"
            :key="task.id"
            class="flex items-center justify-between bg-surface px-4 py-3"
          >
            <RouterLink :to="`/tasks/${task.id}`" class="link text-sm font-medium text-action">
              {{ task.name }}
            </RouterLink>
            <span class="text-sm tabular-nums text-muted">
              {{ formatDate(task.next_run_at) }}
            </span>
          </li>
        </ul>
      </section>

      <p v-if="data?.oldest_due_at" class="mt-4 text-sm text-muted">
        {{
          $t('dashboard.oldestDue', {
            at: formatDate(data.oldest_due_at),
          })
        }}
      </p>

      <p v-if="stats.every((s) => s.value === 0)" class="mt-8 text-center text-muted">
        {{ $t('dashboard.empty') }}
      </p>
    </template>
  </div>
</template>
