<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import BidiText from '@/components/ui/BidiText.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { EndpointProfile, EndpointTestResult } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'
import { semanticIcons } from '@/utils/icons'

const { t } = useI18n()
const tenant = useTenantStore()

const testingId = ref<string | null>(null)
const actionError = ref<string | null>(null)
const actionMessage = ref<string | null>(null)

const { data, loading, error, reload } = useAsyncData(async () => {
  if (!tenant.currentTenantId || !tenant.currentEnvironmentId) {
    return []
  }
  const { data: response } = await api.get<{ data: EndpointProfile[] }>(
    tenant.tenantPath('/endpoint-profiles'),
  )
  return response.data ?? []
})

async function onTest(profile: EndpointProfile): Promise<void> {
  if (testingId.value) {
    return
  }
  testingId.value = profile.id
  actionError.value = null
  actionMessage.value = null
  try {
    const { data: response } = await api.post<{ data: EndpointTestResult }>(
      tenant.tenantPath(`/endpoint-profiles/${profile.id}/test`),
      {},
    )
    const result = response.data
    if (result?.transport_error_code) {
      actionError.value =
        result.transport_error_code || t('endpointProfiles.testError')
    } else {
      actionMessage.value = t('endpointProfiles.testSuccess')
    }
  } catch (err) {
    actionError.value =
      err instanceof ApiError ? err.message : t('endpointProfiles.testError')
  } finally {
    testingId.value = null
  }
}
</script>

<template>
  <div>
    <div class="page-header">
      <PageHeader
        :title="$t('endpointProfiles.title')"
        :subtitle="$t('endpointProfiles.subtitle')"
      />
      <RouterLink
        to="/endpoint-profiles/new"
        class="primary-action shrink-0"
      >
        {{ $t('endpointProfiles.create') }}
      </RouterLink>
    </div>

    <BaseAlert v-if="actionError" tone="danger" role="alert" class="mb-4">
      {{ actionError }}
    </BaseAlert>
    <BaseAlert v-else-if="actionMessage" tone="success" role="status" class="mb-4">
      {{ actionMessage }}
    </BaseAlert>

    <LoadingState v-if="loading" />
    <ErrorState
      v-else-if="error"
      :message="error ?? $t('endpointProfiles.loadError')"
      @retry="reload"
    />
    <EmptyState
      v-else-if="!tenant.currentTenantId || !tenant.currentEnvironmentId"
      :message="$t('endpointProfiles.needsTenant')"
    />
    <EmptyState v-else-if="!data?.length" :message="$t('endpointProfiles.empty')">
      <p>{{ $t('endpointProfiles.empty') }}</p>
      <p class="mt-2 text-sm">{{ $t('endpointProfiles.emptyHint') }}</p>
      <RouterLink to="/endpoint-profiles/new" class="primary-action mt-4">
        {{ $t('endpointProfiles.create') }}
      </RouterLink>
    </EmptyState>
    <div v-else class="table-scroll" role="region" tabindex="0" :aria-label="$t('common.table.scrollRegion')">
      <table class="min-w-full divide-y divide-border">
        <thead class="bg-surface">
          <tr>
            <th class="px-4 py-3 text-start text-sm font-medium text-muted">
              {{ $t('common.name') }}
            </th>
            <th class="px-4 py-3 text-start text-sm font-medium text-muted">
              {{ $t('endpointProfiles.method') }}
            </th>
            <th class="px-4 py-3 text-start text-sm font-medium text-muted">
              {{ $t('endpointProfiles.detail.baseUrl') }}
            </th>
            <th class="px-4 py-3 text-start text-sm font-medium text-muted">
              {{ $t('common.status') }}
            </th>
            <th class="px-4 py-3 text-end text-sm font-medium text-muted">
              {{ $t('common.actions') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border bg-surface">
          <tr v-for="profile in data" :key="profile.id">
            <td class="px-4 py-3">
              <RouterLink :to="`/endpoint-profiles/${profile.id}`" class="link font-medium text-action-text">
                {{ profile.name }}
              </RouterLink>
            </td>
            <td class="px-4 py-3">
              <span class="rounded bg-surface-emphasis px-2 py-0.5 font-mono text-xs text-text">
                {{ profile.method }}
              </span>
            </td>
            <td class="max-w-sm whitespace-normal break-all px-4 py-3 font-mono text-sm text-muted">
                  <BidiText :value="profile.base_url" />
            </td>
            <td class="px-4 py-3 text-sm">
              <BaseBadge
                :label="profile.enabled ? $t('endpointProfiles.enabled') : $t('endpointProfiles.disabled')"
                :tone="profile.enabled ? 'success' : 'neutral'"
                :icon="profile.enabled ? semanticIcons.success : semanticIcons.neutral"
              />
            </td>
            <td class="[&>*+*]:ms-3 px-4 py-3 text-end text-sm">
              <button
                type="button"
                class="link text-action-text disabled:opacity-60"
                :disabled="testingId === profile.id"
                @click="onTest(profile)"
              >
                {{
                  testingId === profile.id
                    ? $t('endpointProfiles.test.running')
                    : $t('endpointProfiles.detail.test')
                }}
              </button>
              <RouterLink :to="`/endpoint-profiles/${profile.id}`" class="link text-action-text">
                {{ $t('endpointProfiles.view') }}
              </RouterLink>
              <RouterLink :to="`/endpoint-profiles/${profile.id}/edit`" class="link text-action-text">
                {{ $t('common.edit') }}
              </RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
