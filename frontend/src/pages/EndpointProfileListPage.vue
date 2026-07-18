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
  const { data: response } = await api.get<{ data: EndpointProfile[] }>(
    tenant.tenantPath('/endpoint-profiles'),
  )
  return response.data ?? []
})
</script>

<template>
  <div>
    <PageHeader
      :title="$t('endpointProfiles.title')"
      :subtitle="$t('endpointProfiles.subtitle')"
    />

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('endpointProfiles.loadError')"
      @retry="reload"
    />
    <div
      v-else-if="!data?.length"
      class="rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500"
    >
      {{ $t('endpointProfiles.empty') }}
    </div>
    <ul v-else class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
      <li v-for="profile in data" :key="profile.id" class="flex items-center justify-between bg-white px-4 py-3 dark:bg-gray-900">
        <div>
          <p class="font-medium">{{ profile.name }}</p>
          <p class="text-sm text-gray-500">{{ profile.base_url }}</p>
        </div>
        <RouterLink
          :to="`/endpoint-profiles/${profile.id}`"
          class="text-sm text-violet-600 hover:underline"
        >
          {{ $t('common.edit') }}
        </RouterLink>
      </li>
    </ul>
  </div>
</template>
