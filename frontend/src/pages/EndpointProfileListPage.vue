<script setup lang="ts">
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { EndpointProfile } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const tenant = useTenantStore()

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    return []
  }
  const { data: response } = await api.get<{ data: EndpointProfile[] }>(
    tenant.tenantPath('/endpoint-profiles'),
  )
  return response.data ?? []
})
</script>

<template>
  <div>
    <div class="mb-8 flex items-start justify-between gap-4">
      <PageHeader
        :title="$t('endpointProfiles.title')"
        :subtitle="$t('endpointProfiles.subtitle')"
      />
      <RouterLink
        to="/endpoint-profiles/new"
        class="shrink-0 rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
      >
        {{ $t('endpointProfiles.create') }}
      </RouterLink>
    </div>

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('endpointProfiles.loadError')"
      @retry="reload"
    />
    <div
      v-else-if="!tenant.currentTenantId || !tenant.currentEnvironmentId"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('endpointProfiles.needsTenant') }}
    </div>
    <div
      v-else-if="!data?.length"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      <p>{{ $t('endpointProfiles.empty') }}</p>
      <p class="mt-2 text-sm">{{ $t('endpointProfiles.emptyHint') }}</p>
      <RouterLink
        to="/endpoint-profiles/new"
        class="mt-4 inline-block text-sm text-violet-600 hover:underline"
      >
        {{ $t('endpointProfiles.create') }}
      </RouterLink>
    </div>
    <div
      v-else
      class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800"
    >
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
        <thead class="bg-gray-50 dark:bg-gray-900">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('common.name') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('endpointProfiles.method') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('endpointProfiles.detail.baseUrl') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
              {{ $t('common.status') }}
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">
              {{ $t('common.actions') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-950">
          <tr v-for="profile in data" :key="profile.id">
            <td class="px-4 py-3">
              <RouterLink
                :to="`/endpoint-profiles/${profile.id}`"
                class="font-medium text-violet-600 hover:underline"
              >
                {{ profile.name }}
              </RouterLink>
            </td>
            <td class="px-4 py-3">
              <span
                class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-200"
              >
                {{ profile.method }}
              </span>
            </td>
            <td class="max-w-xs truncate px-4 py-3 font-mono text-sm text-gray-600">
              {{ profile.base_url }}
            </td>
            <td class="px-4 py-3 text-sm">
              <span
                :class="
                  profile.enabled
                    ? 'text-green-700'
                    : 'text-gray-500'
                "
              >
                {{
                  profile.enabled
                    ? $t('endpointProfiles.enabled')
                    : $t('endpointProfiles.disabled')
                }}
              </span>
            </td>
            <td class="space-x-3 px-4 py-3 text-right text-sm">
              <RouterLink
                :to="`/endpoint-profiles/${profile.id}`"
                class="text-violet-600 hover:underline"
              >
                {{ $t('endpointProfiles.view') }}
              </RouterLink>
              <RouterLink
                :to="`/endpoint-profiles/${profile.id}/edit`"
                class="text-violet-600 hover:underline"
              >
                {{ $t('common.edit') }}
              </RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
