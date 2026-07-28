<script setup lang="ts">
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { AuditLog } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const tenant = useTenantStore()

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId) {
    return [] as AuditLog[]
  }

  const { data: response } = await api.get<{ data: AuditLog[] }>(
    `/tenants/${tenant.currentTenantId}/audit-logs?per_page=100`,
  )
  return response.data ?? []
})

function formatWhen(value?: string | null): string {
  if (!value) {
    return '—'
  }
  try {
    return new Intl.DateTimeFormat(undefined, {
      dateStyle: 'medium',
      timeStyle: 'medium',
    }).format(new Date(value))
  } catch {
    return value
  }
}
</script>

<template>
  <div>
    <PageHeader :title="$t('settings.audit.title')" :subtitle="$t('settings.audit.subtitle')" />

    <EmptyState v-if="!tenant.currentTenantId" :message="$t('settings.audit.needsTenant')" />
    <LoadingState v-else-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('settings.audit.loadError')"
      @retry="reload"
    />
    <EmptyState v-else-if="!data?.length" :message="$t('settings.audit.empty')" />
    <div v-else class="overflow-hidden rounded-lg border border-border">
      <table class="min-w-full divide-y divide-border">
        <thead class="bg-surface">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('settings.audit.fields.when') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('settings.audit.fields.action') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('settings.audit.fields.resource') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('settings.audit.fields.actor') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted">
              {{ $t('settings.audit.fields.requestId') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border bg-surface">
          <tr v-for="log in data" :key="log.id">
            <td class="px-4 py-3 text-sm tabular-nums text-text">{{ formatWhen(log.created_at) }}</td>
            <td class="px-4 py-3 text-sm font-medium text-text">{{ log.action }}</td>
            <td class="px-4 py-3 text-sm text-muted">
              {{ log.resource_type }}
              <span v-if="log.resource_id"> · {{ log.resource_id }}</span>
            </td>
            <td class="px-4 py-3 text-sm text-muted">
              {{ log.actor?.email ?? '—' }}
            </td>
            <td class="px-4 py-3 font-mono text-xs text-muted">
              {{ log.request_id ?? '—' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
