<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { formatDateTime, formatUnit } from '@/i18n/format'
import api from '@/services/api'
import type { PlatformHealth } from '@/services/types'
import { semanticIcons } from '@/utils/icons'
import { toneForPlatformHealth } from '@/utils/statusTone'

const { locale, t } = useI18n()

const { data, loading, error, reload } = useAsyncData(async () => {
  // PlatformHealthController returns a flat JSON object (not wrapped in `data`).
  const { data: response } = await api.get<PlatformHealth>('/platform/health')
  return response
})

function formatDate(value?: string | null): string {
  return value ? formatDateTime(value, locale.value) : '—'
}

function retentionUnit(value: number | undefined, unit: 'day' | 'hour'): string {
  if (value === undefined) return '—'
  return formatUnit(value, unit, locale.value, (key, named) => t(key, named))
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
      <div class="mb-6">
        <BaseBadge
          :label="$t(`settings.platformHealth.status.${data.status}`, data.status)"
          :tone="toneForPlatformHealth(data.status).tone"
          :icon="toneForPlatformHealth(data.status).icon"
        />
      </div>

      <BaseCard>
        <dl class="grid gap-4 sm:grid-cols-2">
        <div>
          <dt class="text-sm text-muted">
            {{ $t('settings.platformHealth.fields.database') }}
          </dt>
          <dd class="mt-1 text-sm font-medium text-text">{{ data.database }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">
            {{ $t('settings.platformHealth.fields.version') }}
          </dt>
          <dd class="mt-1 text-sm font-medium text-text">{{ data.version }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">
            {{ $t('settings.platformHealth.fields.scheduler') }}
          </dt>
          <dd class="mt-1 text-sm tabular-nums text-text">
            {{ formatDate(data.scheduler_last_seen_at) }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">
            {{ $t('settings.platformHealth.fields.retryExecutor') }}
          </dt>
          <dd class="mt-1 text-sm tabular-nums text-text">
            {{ formatDate(data.retry_executor_last_seen_at) }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">
            {{ $t('settings.platformHealth.fields.maintenance') }}
          </dt>
          <dd class="mt-1 text-sm tabular-nums text-text">
            {{ formatDate(data.maintenance_last_seen_at) }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">
            {{ $t('settings.platformHealth.fields.schedulerStale') }}
          </dt>
          <dd class="mt-1 text-sm">
            <BaseBadge
              :label="staleLabel(data.scheduler_stale)"
              :tone="data.scheduler_stale ? 'warning' : 'neutral'"
              :icon="data.scheduler_stale ? semanticIcons.warning : semanticIcons.success"
            />
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">
            {{ $t('settings.platformHealth.fields.retryExecutorStale') }}
          </dt>
          <dd class="mt-1 text-sm">
            <BaseBadge
              :label="staleLabel(data.retry_executor_stale)"
              :tone="data.retry_executor_stale ? 'warning' : 'neutral'"
              :icon="data.retry_executor_stale ? semanticIcons.warning : semanticIcons.success"
            />
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">
            {{ $t('settings.platformHealth.fields.staleClaims') }}
          </dt>
          <dd class="mt-1 text-sm font-medium tabular-nums text-text">{{ data.stale_claims }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">
            {{ $t('settings.platformHealth.fields.pendingRuns') }}
          </dt>
          <dd class="mt-1 text-sm font-medium tabular-nums text-text">{{ data.pending_runs }}</dd>
        </div>
        </dl>
      </BaseCard>

      <section v-if="data.retention" class="mt-6">
        <h2 class="mb-3 text-lg font-medium">
          {{ $t('settings.platformHealth.fields.retention') }}
        </h2>
        <BaseCard>
          <dl class="grid gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-sm text-muted">
              {{ $t('settings.retention.fields.payloadSnapshotsDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium tabular-nums text-text">
              {{ retentionUnit(data.retention.payload_snapshots_days, 'day') }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-muted">
              {{ $t('settings.retention.fields.attemptMetadataDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium tabular-nums text-text">
              {{ retentionUnit(data.retention.attempt_metadata_days, 'day') }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-muted">
              {{ $t('settings.retention.fields.runSummaryDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium tabular-nums text-text">
              {{ retentionUnit(data.retention.run_summary_days, 'day') }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-muted">
              {{ $t('settings.retention.fields.auditLogsDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium tabular-nums text-text">
              {{ retentionUnit(data.retention.audit_logs_days, 'day') }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-muted">
              {{ $t('settings.retention.fields.apiIdempotencyHours') }}
            </dt>
            <dd class="mt-1 text-sm font-medium tabular-nums text-text">
              {{ retentionUnit(data.retention.api_idempotency_hours, 'hour') }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-muted">
              {{ $t('settings.retention.fields.systemHeartbeatDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium tabular-nums text-text">
              {{ retentionUnit(data.retention.system_heartbeat_days, 'day') }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-muted">
              {{ $t('settings.retention.fields.deadRunsDays') }}
            </dt>
            <dd class="mt-1 text-sm font-medium tabular-nums text-text">
              {{ retentionUnit(data.retention.dead_runs_days, 'day') }}
            </dd>
          </div>
          </dl>
        </BaseCard>
      </section>
    </template>
  </div>
</template>
