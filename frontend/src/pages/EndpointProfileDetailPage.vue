<script setup lang="ts">
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import api from '@/services/api'
import type { EndpointProfile } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'

const props = defineProps<{ id: string }>()
const tenant = useTenantStore()

const { data, loading, error, reload } = useAsyncData(async () => {
  const { data: response } = await api.get<{ data: EndpointProfile }>(
    tenant.tenantPath(`/endpoint-profiles/${props.id}`),
  )
  return response.data
})
</script>

<template>
  <div>
    <div class="mb-4">
      <RouterLink to="/endpoint-profiles" class="text-sm text-violet-600 hover:underline">
        ← {{ $t('common.back') }}
      </RouterLink>
    </div>

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('endpointProfiles.loadError')"
      @retry="reload"
    />
    <template v-else-if="data">
      <PageHeader :title="data.name" :subtitle="$t('endpointProfiles.detail.title')" />
      <dl class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <dt class="text-sm text-gray-500">{{ $t('endpointProfiles.detail.baseUrl') }}</dt>
        <dd class="mt-1 font-mono text-sm">{{ data.base_url }}</dd>
      </dl>
    </template>
  </div>
</template>
