<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { PlatformHealth } from '@/services/types'

const { locale, t } = useI18n()

const { data, loading, error, reload } = useAsyncData(async () => {
  // PlatformHealthController returns a flat JSON object (not wrapped in `data`).
  const { data: response } = await api.get<PlatformHealth>('/platform/health')
  return response
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

function staleLabel(stale?: boolean): string {
  if (stale === true) {
    return t('settings.platformHealth.fields.staleYes')
  }
  if (stale === false) {
    return t('settings.platformHealth.fields.staleNo')
  }
  return '—'
}
</script>

<template>
  <div>
    <PageHeader
      :title="$t('settings.platformHealth.title')"
      :subtitle="$t('settings.platformHealth.subtitle')"
    />

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('settings.platformHealth.loadError')"
      @retry="reload"
    />
    <template v-else-if="data">
      <div
        class="mb-6 inline-flex rounded-full px-3 py-1 text-sm font-medium"
        :class="{
          'bg-green-100 text-green-800': data.status === 'healthy',
          'bg-yellow-100 text-yellow-800': data.status === 'degraded',
        }"
      >
        {{ $t(`settings.platformHealth.status.${data.status}`, data.status) }}
      </div>

      <dl class="grid gap-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 sm:grid-cols-2">
        <div>
          <dt class="text-sm text-gray-500">
            {{ $t('settings.platformHealth.fields.database') }}
          </dt>
          <dd class="mt-1 text-sm font-medium">{{ data.database }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">
            {{ $t('settings.platformHealth.fields.version') }}
          </dt>
          <dd class="mt-1 text-sm font-medium">{{ data.version }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">
            {{ $t('settings.platformHealth.fields.scheduler') }}
          </dt>
          <dd class="mt-1 text-sm">
            {{ formatDate(data.scheduler_last_seen_at) }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">
            {{ $t('settings.platformHealth.fields.retryExecutor') }}
          </dt>
          <dd class="mt-1 text-sm">
            {{ formatDate(data.retry_executor_last_seen_at) }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">
            {{ $t('settings.platformHealth.fields.maintenance') }}
          </dt>
          <dd class="mt-1 text-sm">
            {{ formatDate(data.maintenance_last_seen_at) }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">
            {{ $t('settings.platformHealth.fields.schedulerStale') }}
          </dt>
          <dd
            class="mt-1 text-sm font-medium"
            :class="data.scheduler_stale ? 'text-amber-700 dark:text-amber-400' : ''"
          >
            {{ staleLabel(data.scheduler_stale) }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">
            {{ $t('settings.platformHealth.fields.retryExecutorStale') }}
          </dt>
          <dd
            class="mt-1 text-sm font-medium"
            :class="data.retry_executor_stale ? 'text-amber-700 dark:text-amber-400' : ''"
          >
            {{ staleLabel(data.retry_executor_stale) }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">
            {{ $t('settings.platformHealth.fields.staleClaims') }}
          </dt>
          <dd class="mt-1 text-sm font-medium">{{ data.stale_claims }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">
            {{ $t('settings.platformHealth.fields.pendingRuns') }}
          </dt>
          <dd class="mt-1 text-sm font-medium">{{ data.pending_runs }}</dd>
        </div>
      </dl>

      <section v-if="data.retention" class="mt-6">
        <h2 class="mb-3 text-lg font-medium">
          {{ $t('settings.platformHealth.fields.retention') }}
        </h2>
        <dl class="grid gap-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 sm:grid-cols-2">
          <div>
            <dt class="text-sm text-gray-500">
              {{ $t('settings.retention.fields.payloadSnapshotsDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium">
              {{ data.retention.payload_snapshots_days }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">
              {{ $t('settings.retention.fields.attemptMetadataDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium">
              {{ data.retention.attempt_metadata_days }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">
              {{ $t('settings.retention.fields.runSummaryDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium">
              {{ data.retention.run_summary_days }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">
              {{ $t('settings.retention.fields.auditLogsDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium">
              {{ data.retention.audit_logs_days }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">
              {{ $t('settings.retention.fields.apiIdempotencyHours') }}
            </dt>
            <dd class="mt-1 text-sm font-medium">
              {{ data.retention.api_idempotency_hours }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">
              {{ $t('settings.retention.fields.systemHeartbeatDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium">
              {{ data.retention.system_heartbeat_days }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">
              {{ $t('settings.retention.fields.deadRunsDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium">
              {{ data.retention.dead_runs_days ?? '—' }}
            </dd>
          </div>
        </dl>
      </section>
    </template>
  </div>
</template>
