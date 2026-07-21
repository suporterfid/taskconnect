<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { PlatformHealth } from '@/services/types'

const { locale } = useI18n()

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
    </template>
  </div>
</template>
