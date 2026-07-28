<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'

import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import CodeBlock from '@/components/ui/CodeBlock.vue'
import { useAsyncData } from '@/composables/useAsyncData'
import { ApiError } from '@/services/api'
import api from '@/services/api'
import type { EndpointProfile, EndpointTestResult } from '@/services/types'
import { useTenantStore } from '@/stores/tenant'
import { semanticIcons } from '@/utils/icons'

const props = defineProps<{ id: string }>()
const { t } = useI18n()
const router = useRouter()
const tenant = useTenantStore()

const { data, loading, error, reload } = useAsyncData(async () => {
  const { data: response } = await api.get<{ data: EndpointProfile }>(
    tenant.tenantPath(`/endpoint-profiles/${props.id}`),
  )
  return response.data
})

const headerEntries = computed(() =>
  Object.entries(data.value?.headers ?? {}),
)

const archiving = ref(false)
const testing = ref(false)
const actionError = ref<string | null>(null)
const testPath = ref('')
const testBody = ref('')
const testResult = ref<EndpointTestResult | null>(null)

async function onArchive(): Promise<void> {
  if (!confirm(t('endpointProfiles.archiveConfirm'))) {
    return
  }
  archiving.value = true
  actionError.value = null
  try {
    await api.delete(tenant.tenantPath(`/endpoint-profiles/${props.id}`))
    await router.push('/endpoint-profiles')
  } catch (err) {
    actionError.value =
      err instanceof ApiError
        ? err.message
        : t('endpointProfiles.archiveError')
  } finally {
    archiving.value = false
  }
}

async function onTest(): Promise<void> {
  testing.value = true
  actionError.value = null
  testResult.value = null
  try {
    const payload: { path?: string; body?: string } = {}
    if (testPath.value.trim()) {
      payload.path = testPath.value.trim()
    }
    if (testBody.value.trim()) {
      payload.body = testBody.value
    }
    const { data: response } = await api.post<{ data: EndpointTestResult }>(
      tenant.tenantPath(`/endpoint-profiles/${props.id}/test`),
      payload,
    )
    testResult.value = response.data
  } catch (err) {
    actionError.value =
      err instanceof ApiError
        ? err.message
        : t('endpointProfiles.testError')
  } finally {
    testing.value = false
  }
}
</script>

<template>
  <div>
    <div class="mb-4">
      <RouterLink to="/endpoint-profiles" class="link text-sm text-action">
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
      <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <PageHeader
          :title="data.name"
          :subtitle="$t('endpointProfiles.detail.title')"
        />
        <div class="flex flex-wrap gap-2">
          <RouterLink
            :to="`/endpoint-profiles/${data.id}/edit`"
            class="inline-flex items-center rounded-md bg-action px-4 py-2 text-sm font-medium text-white hover:bg-action-hover"
          >
            {{ $t('common.edit') }}
          </RouterLink>
          <BaseButton variant="danger" :disabled="archiving" @click="onArchive">
            {{ $t('endpointProfiles.archive') }}
          </BaseButton>
        </div>
      </div>

      <BaseAlert v-if="actionError" tone="danger" role="alert" class="mb-4">
        {{ actionError }}
      </BaseAlert>

      <BaseCard class="mb-6">
        <dl class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <dt class="text-sm text-muted">
            {{ $t('endpointProfiles.detail.baseUrl') }}
          </dt>
          <dd class="mt-1 break-all font-mono text-sm text-text">{{ data.base_url }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">
            {{ $t('endpointProfiles.fields.method') }}
          </dt>
          <dd class="mt-1 font-mono text-sm text-text">{{ data.method }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">{{ $t('common.status') }}</dt>
          <dd class="mt-1 text-sm">
            <BaseBadge
              :label="data.enabled ? $t('endpointProfiles.enabled') : $t('endpointProfiles.disabled')"
              :tone="data.enabled ? 'success' : 'neutral'"
              :icon="data.enabled ? semanticIcons.success : semanticIcons.neutral"
            />
          </dd>
        </div>
        <div v-if="data.description" class="sm:col-span-2">
          <dt class="text-sm text-muted">
            {{ $t('common.description') }}
          </dt>
          <dd class="mt-1 text-sm text-text">{{ data.description }}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted">
            {{ $t('endpointProfiles.detail.auth') }}
          </dt>
          <dd class="mt-1 text-sm text-text">
            {{ $t(`endpointProfiles.authModes.${data.auth_mode}`) }}
            <span v-if="data.auth_header_name" class="ml-1 font-mono text-muted">{{ data.auth_header_name }}</span>
            <span v-if="data.auth_query_param" class="ml-1 font-mono text-muted">?{{ data.auth_query_param }}</span>
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">
            {{ $t('endpointProfiles.detail.secret') }}
          </dt>
          <dd class="mt-1 font-mono text-sm text-text">
            {{ data.secret_id || $t('endpointProfiles.detail.none') }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">
            {{ $t('endpointProfiles.detail.timeouts') }}
          </dt>
          <dd class="mt-1 text-sm tabular-nums text-text">
            {{ data.connect_timeout }}s /
            {{ data.total_timeout }}s
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted">
            {{ $t('endpointProfiles.detail.security') }}
          </dt>
          <dd class="mt-1 text-sm text-text">
            TLS:
            {{
              data.verify_tls
                ? $t('endpointProfiles.fields.verifyTls')
                : $t('endpointProfiles.disabled')
            }}
            ·
            {{
              data.follow_redirects
                ? $t('endpointProfiles.fields.followRedirects')
                : $t('endpointProfiles.disabled')
            }}
          </dd>
        </div>
        <div v-if="data.allowed_path_prefix" class="sm:col-span-2">
          <dt class="text-sm text-muted">
            {{ $t('endpointProfiles.fields.allowedPathPrefix') }}
          </dt>
          <dd class="mt-1 font-mono text-sm text-text">
            {{ data.allowed_path_prefix }}
          </dd>
        </div>
        <div class="sm:col-span-2">
          <dt class="text-sm text-muted">
            {{ $t('endpointProfiles.detail.headers') }}
          </dt>
          <dd class="mt-1">
            <p v-if="!headerEntries.length" class="text-sm text-muted">
              {{ $t('endpointProfiles.detail.noHeaders') }}
            </p>
            <ul v-else class="space-y-1 font-mono text-sm text-text">
              <li v-for="[key, value] in headerEntries" :key="key">
                <span class="text-muted">{{ key }}:</span>
                {{ value }}
              </li>
            </ul>
          </dd>
        </div>
        </dl>
      </BaseCard>

      <BaseCard>
        <h2 class="text-lg font-semibold text-text">
          {{ $t('endpointProfiles.test.title') }}
        </h2>
        <p class="mt-1 text-sm text-muted">
          {{ $t('endpointProfiles.test.subtitle') }}
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <label class="block sm:col-span-2">
            <span class="mb-1 block text-sm font-medium text-text">{{
              $t('endpointProfiles.test.path')
            }}</span>
            <BaseInput
              v-model="testPath"
              class="font-mono text-sm"
              :placeholder="$t('endpointProfiles.test.pathPlaceholder')"
            />
          </label>
          <label class="block sm:col-span-2">
            <span class="mb-1 block text-sm font-medium text-text">{{
              $t('endpointProfiles.test.body')
            }}</span>
            <BaseTextarea v-model="testBody" :rows="3" class="font-mono text-sm" />
          </label>
        </div>

        <BaseButton class="mt-4" :disabled="testing" @click="onTest">
          {{
            testing
              ? $t('endpointProfiles.test.running')
              : $t('endpointProfiles.test.run')
          }}
        </BaseButton>

        <div v-if="testResult" class="mt-6 space-y-3 rounded-md border border-border bg-surface-emphasis p-4">
          <h3 class="text-sm font-semibold text-text">
            {{ $t('endpointProfiles.test.result') }}
          </h3>
          <p class="text-sm text-text">
            <span class="text-muted">{{ $t('endpointProfiles.test.status') }}:</span>
            {{
              testResult.response_status ??
                $t('endpointProfiles.test.noStatus')
            }}
          </p>
          <p class="break-all font-mono text-sm text-text">
            <span class="text-muted">{{ $t('endpointProfiles.test.url') }}:</span>
            {{ testResult.request_url_redacted }}
          </p>
          <BaseAlert v-if="testResult.transport_error_code" tone="danger" role="alert">
            <span class="font-medium">{{
              $t('endpointProfiles.test.transportError')
            }}:</span>
            {{ testResult.transport_error_code }}
          </BaseAlert>
          <div v-if="testResult.request_headers_redacted">
            <CodeBlock :label="$t('endpointProfiles.test.responseHeaders')">{{
              JSON.stringify(testResult.request_headers_redacted, null, 2)
            }}</CodeBlock>
          </div>
          <div v-if="testResult.response_body_truncated">
            <CodeBlock :label="$t('endpointProfiles.test.responseBody')">{{
              testResult.response_body_truncated
            }}</CodeBlock>
          </div>
        </div>
      </BaseCard>
    </template>
  </div>
</template>
