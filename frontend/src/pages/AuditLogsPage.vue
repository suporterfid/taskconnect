<script setup lang="ts">
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
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

    <p
      v-if="!tenant.currentTenantId"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('settings.audit.needsTenant') }}
    </p>
    <LoadingState v-else-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('settings.audit.loadError')"
      @retry="reload"
    />
    <p
      v-else-if="!data?.length"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('settings.audit.empty') }}
    </p>
    <div v-else class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
        <thead class="bg-gray-50 dark:bg-gray-900">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('settings.audit.fields.when') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('settings.audit.fields.action') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('settings.audit.fields.resource') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('settings.audit.fields.actor') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('settings.audit.fields.requestId') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-950">
          <tr v-for="log in data" :key="log.id">
            <td class="px-4 py-3 text-sm">{{ formatWhen(log.created_at) }}</td>
            <td class="px-4 py-3 text-sm font-medium">{{ log.action }}</td>
            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
              {{ log.resource_type }}
              <span v-if="log.resource_id"> · {{ log.resource_id }}</span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
              {{ log.actor?.email ?? '—' }}
            </td>
            <td class="px-4 py-3 font-mono text-xs text-gray-500">
              {{ log.request_id ?? '—' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
